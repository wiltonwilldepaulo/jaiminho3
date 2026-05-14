<?php

namespace app\controller;

final class Login extends Base
{
    public function login($request, $response)
    {
        try {
            return $this->getTwig()
                ->render($response, $this->setView('login'), [
                    'titulo' => 'Início',
                ])
                ->withHeader('Content-Type', 'text/html')
                ->withStatus(200);
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }
    }
    public function authenticate($request, $response)
    {
        # Recupera as credenciais enviadas no corpo da requisição
        $form = $request->getParsedBody();
        $login = $form['login'] ?? null;
        $senha = $form['senha'] ?? null;
        # Bloqueia se algum campo veio vazio
        if (is_null($login) || is_null($senha)) {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe seu usuário e senha!', 'id' => 0]);
        }
        # Verifica se a sessão está em "lockout" por excesso de tentativas falhas
        if (isset($_SESSION['login_locked_until']) && $_SESSION['login_locked_until'] > time()) {
            return $this->json($response, ['status' => false, 'msg' => 'Muitas tentativas. Tente novamente em alguns minutos.', 'id' => 0], 429);
        }

        try {
            # Começa a montar a query: SELECT * FROM vw_user
            $qb = \app\database\DB::select('*')
                ->from('vw_user');

            # Define o valor que será procurado nos três campos
            # O Doctrine cria um "placeholder seguro" no lugar do valor real,
            # protegendo a aplicação contra SQL injection.
            $placeholder = $qb->createNamedParameter($login);

            # Monta a cláusula WHERE com três condições ligadas por OR:
            # WHERE cpf = :login OR email = :login OR whatsapp = :login
            $qb->where('cpf = ' . $placeholder)
                ->orWhere('email = '    . $placeholder)
                ->orWhere('whatsapp = ' . $placeholder);

            # Executa a query e busca um único registro (a primeira linha encontrada)
            $user = $qb->fetchAssociative();

            # Hash bcrypt pré-computado e inválido, usado quando o usuário não existe (proteção contra timing attack)
            $dummyHash = '$2y$10$CwTycUXWue0Thq9StjUM0uJ8.k3.kK1m3Sv7lJ1uG9N9Yvb.MqYsa';

            # Sempre executa password_verify, mesmo sem usuário, para manter tempo de resposta constante
            $senhaValida = password_verify($senha, $user['senha'] ?? $dummyHash);

            # Falha de autenticação: mensagem genérica + contador de tentativas
            if (!$user || !$senhaValida) {
                # Incrementa o contador de tentativas falhas da sessão atual
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                # Após 5 falhas, bloqueia novas tentativas por 15 minutos (rate limiting básico)
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_locked_until'] = time() + 900;
                    $_SESSION['login_attempts'] = 0;
                }
                return $this->json($response, ['status' => false, 'msg' => 'Verifique seu e-mail e senha e tente novamente!', 'id' => 0], 403);
            }

            # Login válido: zera contadores de tentativa e lockout
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

            # Regenera o ID da sessão para mitigar session fixation
            session_regenerate_id(true);

            # Renova o hash da senha se o algoritmo/custo padrão tiver mudado
            if (password_needs_rehash($user['senha'], PASSWORD_DEFAULT)) {
                \app\database\DB::connection()->update(
                    'users',
                    [
                        'senha'         => password_hash($senha, PASSWORD_DEFAULT),
                        'atualizado_em' => date('Y-m-d H:i:s'),
                    ],
                    ['id' => $user['id']],
                );
            }

            # Remove o hash da senha antes de gravar o usuário na sessão (evita expor credencial)
            unset($user['senha']);

            # Persiste o usuário autenticado na sessão (fonte de verdade do estado)
            $_SESSION['user'] = $user;
            $_SESSION['user']['logado'] = true;

            # Calcula o tempo de vida da sessão a partir do php.ini, com fallback de 3600s
            $lifetime = (int) (ini_get('session.gc_maxlifetime') ?: 3600);

            # Cacheia o timestamp atual para manter coerência entre iat, nbf e exp
            $now = time();

            # Identificador único deste token, em hex de 32 caracteres (16 bytes random_bytes)
            # Permite revogar tokens individualmente via denylist no Redis
            $jti = bin2hex(random_bytes(16));

            # Monta o payload do JWT seguindo a RFC 7519 (Registered Claim Names)
            $payload = [
                'iat' => $now,                  # Issued At: momento de emissão
                'nbf' => $now,                  # Not Before: token só é válido a partir daqui
                'exp' => $now + $lifetime,      # Expiration: expiração alinhada à sessão PHP
                'sub' => (string) $user['id'],  # Subject: ID do usuário autenticado
                'iss' => HOST,                  # Issuer: domínio emissor (mesma constante do cookie)
                'aud' => HOST,                  # Audience: aplicação que vai consumir o token
                'jti' => $jti,                  # JWT ID: identificador único para revogação
            ];

            # Assina o token JWT com a chave secreta da aplicação
            $jwt = \Firebase\JWT\JWT::encode($payload, SECRET_KEY, 'HS256');

            # Determina se a conexão está em HTTPS (define o atributo Secure do cookie)
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

            # Define o cookie auth_token usando domain (constante de configuração, imune a Host Header Injection)
            setcookie('auth_token', $jwt, [
                'expires'  => time() + $lifetime,
                'path'     => '/',
                'domain' => HOST,
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            # Cria um único DateTimeImmutable aproveitando $now já cacheado (coerência com iat/exp do JWT)
            $agora = (new \DateTimeImmutable())->setTimestamp($now);
            # Registra na sessão o horário de criação e o horário previsto de expiração (formato H:i:s correto)
            $_SESSION['user']['sessao_criada_em'] = $agora->format('Y-m-d H:i:s');
            $_SESSION['user']['sessao_expira_em'] = $agora->modify("+{$lifetime} seconds")->format('Y-m-d H:i:s');

            # Retorna a resposta de sucesso ao cliente
            return $this->json($response, [
                'status'           => true,
                'msg'              => 'Seja bem vindo de volta!',
                'id'               => $user['id'],
                'sessao_expira_em' => $_SESSION['user']['sessao_expira_em']
            ], 200);
        } catch (\PDOException $e) {
            # Erro de banco: loga internamente e responde de forma genérica
            error_log('[auth][DB] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível concluir o login. Tente novamente.', 'id' => 0], 500);
        } catch (\UnexpectedValueException | \DomainException $e) {
            # Erro específico do Firebase JWT (chave inválida, payload malformado, etc.)
            error_log('[auth][JWT] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível concluir o login. Tente novamente.', 'id' => 0], 500);
        } catch (\Throwable $e) {
            # Qualquer outra falha inesperada: loga e responde de forma genérica
            error_log('[auth][GERAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro inesperado. Tente novamente ', 'id' => 0], 500);
        }
    }
    public function preRegister($request, $response)
    {
        $form = $request->getParsedBody();
        #Captura os dados informado pelo usuário no formulário de pré-cadastro
        $nome      = $form['nome'] ?? null;
        $sobrenome = $form['sobrenome'] ?? null;
        $cpf       = $form['cpf'] ?? null;
        $rg        = $form['rg'] ?? null;
        $senha     = $form['senha'] ?? null;
        #Dados de contato.
        $email     = $form['email'] ?? null;
        $telefone  = $form['telefone'] ?? null;
        #Criamos o array associativo com os dados do usuário, onde a 
        #chave é o nome da coluna no banco de dados e o valor é o dado 
        #informado pelo usuário.
        $DataUser = [
            'nome'      => $nome,
            'sobrenome' => $sobrenome,
            'cpf'       => $cpf,
            'rg'        => $rg,
            'senha'     => password_hash($senha, PASSWORD_DEFAULT)
        ];
        $id_usuario = 0;
        #Insere os dados no data base com o Docrine e recebe o ID do usuário criado.
        $id_usuario = \app\database\DB::connection()->insert('users', $DataUser);
        #Insere os dados do email do usuário na base.
        $DataEmail = [
            'id_usuario' => $id_usuario,
            'tipo' => 'EMAIL',
            'contato' => $email
        ];
        \app\database\DB::connection()->insert('contact', $DataEmail);
        #Insere os dados do telefone do usuário na base.
        $DataTel = [
            'id_usuario' => $id_usuario,
            'tipo' => 'TELEFONE',
            'contato' => $telefone
        ];
        \app\database\DB::connection()->insert('contact', $DataTel);
        #Retorna a resposta de sucesso ao cliente
        return $this->json($response, [
            'status' => true,
            'msg' => 'Usuário cadastrado com sucesso!'
        ], 200);
    }
    public function google($request, $response)
    {
        $form = $request->getParsedBody();

        $credential = $form['credential'] ?? null;

        $form_g_csrf_token = $form['g_csrf_token'] ?? null;

        $cookie_g_csrf_token = $_COOKIE['g_csrf_token'] ?? null;

        $google_client_id = $_ENV['GOOGLE_CLIENT_ID'] ?? null;

        if (is_null($credential) || is_null($form_g_csrf_token) || is_null($cookie_g_csrf_token)) {
            throw new \InvalidArgumentException('Credential do Google ausente');
        }

        try {


            $provider = new \League\OAuth2\Client\Provider\Google([
                'clientId'     => $google_client_id,
                'clientSecret' => '',
                'redirectUri'  => '',
            ]);

            $httpResponse = $provider->getHttpClient()->request(
                'GET',
                'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential),
                ['timeout' => 3, 'connect_timeout' => 2]
            );

            $claims = json_decode((string) $httpResponse->getBody(), true, flags: JSON_THROW_ON_ERROR);

            $nome = $claims['given_name'];
            $sobrenome = $claims['family_name'];
            $nomecompleto = $claims['name'];
            $email = $claims['email'];
            $foto = $claims['picture'];


            echo "<pre>";
            var_dump($nome, $sobrenome, $nomecompleto, $email, $foto);
            # Atividade anterior dia 14-05-2026

            # 1. Finalizar o processo de autenticação 

            # 2. Opção de sair do sistema onde deve ser destruído a sessão e direcionado para pagina de login novamente
            #_________________________________________________________________________________________________________
            #Com base no e-mail, recuperar os dados de do usuário 
            #utilizando o seguinte script select * from vw_user where email = $email

            #Se retornar dados deve ser verificado o valor do campo ativo, caso seja false,
            # Retorne a seguinte mensagem: Por enquanto você ainda não esta autorizado, por favor aguarde...

            #Caso o valor seja true criar os dados da sessão do usuário e direcionar para pagina de /home ou /adm


        } catch (\Throwable $e) {
            throw new \RuntimeException('Falha na verificação do ID Token do Google: ' . $e->getMessage(), 0, $e);
        }
    }
}
