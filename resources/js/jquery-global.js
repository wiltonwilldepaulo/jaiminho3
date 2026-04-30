/**
 * Inicializador global do jQuery.
 *
 * IMPORTANTE: este módulo precisa ser o PRIMEIRO import em app.js,
 * antes de qualquer plugin que dependa de jQuery (Bootstrap, DataTables,
 * Select2, jQuery Validate, Flatpickr, Inputmask, etc).
 *
 * Justificativa técnica:
 * Os plugins jQuery se auto-registram em window.jQuery quando carregados.
 * Como ES modules executam imports em ordem topológica antes do código
 * procedural, uma única atribuição "window.jQuery = jQuery" no app.js
 * NÃO acontece a tempo — todos os imports rodam antes dela.
 *
 * Isolando essa atribuição em um módulo dedicado e importando-o primeiro,
 * garantimos que window.jQuery exista quando os plugins forem avaliados.
 */
import jQuery from 'jquery'

window.jQuery = jQuery
window.$ = jQuery

export default jQuery