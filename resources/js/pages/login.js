import Swal from "sweetalert2";
import Validate from "../components/validate.js";
import Requests from "../components/requests.js";

const mdPreRegister = document.getElementById('mdPreRegister');
const buttonPreRegister = document.getElementById('buttonPreRegister');
const buttonLogin = document.getElementById('buttonLogin');

mdPreRegister.addEventListener('click', () => {
    $('#modalPreRegisterUser').modal('show');
});

buttonLogin.addEventListener('click', async () => {
    const valid = Validate.SetForm('form').Validate();
    if (!valid) {
        Swal.fire({
            icon: 'error',
            title: 'Ops...',
            text: 'Preencha os campos corretamente!',
            timer: 2500,
            progressBar: true
        });
        return;
    }
    const requests = new Requests();
    const originalText = buttonLogin.textContent;
    try {
        buttonLogin.disabled = true;
        buttonLogin.textContent = 'Autenticando, aguarde...';
        const response = await requests.setForm('form').post('/authentication/auth');
        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Ops...',
                text: response.msg || 'Não foi possivel validar as credenciais tente novamente mais tarde!',
                timer: 2500,
                progressBar: true
            });
            return;
        }
        //window.location.replace('/');
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Ops...',
            text: error.message || 'Restrição: tenta de novo depois',
            timer: 2500,
            progressBar: true
        });
        return;
    } finally {
        buttonLogin.disabled = false;
        buttonLogin.textContent = originalText;
    }
});

buttonPreRegister.addEventListener('click', async () => {

    const validou = Validate.SetForm('form').Validate();

    if (!validou) {
        Swal.fire({
            icon: 'error',
            title: 'Ops...',
            text: 'Preencha os campos corretamente!',
            timer: 2500,
            progressBar: true
        });
        return;
    }

    const requests = new Requests();

    const originalText = buttonPreRegister.textContent;
    try {
        buttonPreRegister.textContent = 'Cadastrando, por favor aguarde...';
        buttonPreRegister.disabled = true;
        const response = await requests.setForm('form').post('/authentication/preregister');

        if (!response.status) {
            Swal.fire({
                icon: 'error',
                title: 'Ops...',
                text: response.message,
                timer: 2500,
                progressBar: true
            });
        }

        Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: response.msg,
            timer: 2500,
            progressBar: true
        }).then(() => {
            $('#modalPreRegisterUser').modal('hide');
        });

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Ops...',
            text: error.message || 'Ocorreu um erro ao cadastrar o usuário!',
            timer: 2500,
            progressBar: true
        });
    } finally {
        buttonPreRegister.disabled = false;
        buttonPreRegister.textContent = originalText;
    }

});
