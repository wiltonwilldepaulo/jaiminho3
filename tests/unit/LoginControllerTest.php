<?php

declare(strict_types=1);

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

test('preRegister com dados válidos retorna 200 status true', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/authentication/preregister')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nome' => 'Wilton',
            'sobrenome' => 'Willl de Paulo',
            'cpf' => '123.123.123-12',
            'rg' => '123456',
            'senha' => '1234',
            'email' => 'wiltonwilldepaulo@gmail.com',
            'telefone' => '69999060839'
        ]);


    $response = (new ResponseFactory())->createResponse();

    $result = (new app\controller\Login())->preRegister($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);
    #Capturamos o código de resposta caso seja 201 significa que o cadastro 
    #Foi criado. 
    expect($result->getStatusCode())->toBe(201);

    expect($json['msg'])->toContain('Usuário cadastrado com sucesso!');

    expect($json['status'])->toBeTrue();
});
