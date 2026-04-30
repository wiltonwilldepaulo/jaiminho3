/**
 * Bootstrap global da aplicação Jaiminho3.
 *
 * Ordem dos imports é DELIBERADA — não reorganize sem entender as
 * dependências de window.jQuery dos plugins jQuery legados.
 */

// =========================================================================
// 1. jQuery global — DEVE ser o primeiro import.
//    Define window.jQuery e window.$ antes de qualquer plugin ser carregado.
// =========================================================================
import './jquery-global.js'

// =========================================================================
// 2. Plugins que dependem de window.jQuery (auto-registram em $.fn)
// =========================================================================

// Bootstrap 5 — JS bundle inclui Popper internamente
import 'bootstrap'

// DataTables core + extensões com tema Bootstrap 5
// Estes pacotes registram $.fn.DataTable, $.fn.dataTable etc. ao serem carregados
import 'datatables.net-bs5'
import 'datatables.net-responsive-bs5'
import 'datatables.net-staterestore-bs5'

// Select2 — registra $.fn.select2
import 'select2'

// jQuery Validate — registra $.fn.validate
import 'jquery-validation'
import 'jquery-validation/dist/localization/messages_pt_BR.js'
import 'jquery-validation/dist/localization/methods_pt.js'

// =========================================================================
// 3. Bibliotecas usadas via construtor global (não via $.fn)
//    Precisam ser expostas explicitamente em window porque seus consumers
//    em resources/js/pages/*.js não as importam — usam como globais.
// =========================================================================

// SweetAlert2 — usado como Swal.fire(...) nos arquivos de página
import Swal from 'sweetalert2'
window.Swal = Swal

import Inputmask from 'inputmask';

window.Inputmask = Inputmask.default ?? Inputmask;

/*// Inputmask — usado como Inputmask({...}).mask("#campo")
import Inputmask from 'inputmask'
window.Inputmask = Inputmask*/

// =========================================================================
// 4. Flatpickr — caso especial: a integração jQuery ($.fn.flatpickr) só
//    é registrada se window.jQuery existir NO MOMENTO do import.
//    Como já garantimos isso no passo 1, o $.fn.flatpickr é populado.
//    Também expomos flatpickr como global para uso direto sem jQuery.
// =========================================================================
import flatpickr from 'flatpickr'
import { Portuguese } from 'flatpickr/dist/l10n/pt.js'

flatpickr.localize(Portuguese)
window.flatpickr = flatpickr