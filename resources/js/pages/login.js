import Swal from "sweetalert2";
import Validate from "../components/validate.js";
import Request from "../components/requests.js";

const mdPreRegister = document.getElementById('mdPreRegister');
const buttonPreRegister = document.getElementById('buttonPreRegister');

mdPreRegister.addEventListener('click', () => {
    $('#modalPreRegisterUser').modal('show');
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

    const requests = new Request();

    const originalText = buttonPreRegister.textContent;
    try {
        buttonPreRegister.textContent = 'Cadastrando, por favor aguarde...';
        buttonPreRegister.disabled = true;
        const response = await requests.setForm('form').post('/');

    } catch (error) {

    } finally {
        buttonPreRegister.disabled = false;
        buttonPreRegister.textContent = originalText;
    }

});
