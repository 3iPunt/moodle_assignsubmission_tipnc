<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Hook callbacks of the plugin.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc;

use assignsubmission_tipnc\api\document;
use assignsubmission_tipnc\api\nextcloud;
use assignsubmission_tipnc\editor\viewmode;
use assignsubmission_tipnc\models\cleanup;
use assignsubmission_tipnc\models\documents;
use assignsubmission_tipnc\models\health;
use assignsubmission_tipnc\output\document_viewer;
use assignsubmission_tipnc\output\enunciate_block;
use assignsubmission_tipnc\output\submission_summary;
use assignsubmission_tipnc\output\unavailable;
use cm_info;
use coding_exception;
use core\hook\output\after_standard_main_region_html_generation;
use core\hook\output\before_http_headers;
use core\hook\output\before_standard_top_of_body_html_generation;
use dml_exception;
use html_writer;
use moodle_url;

/**
 * Hook callbacks of the plugin.
 *
 * mod_assign offers a submission subplugin no place of its own on the assignment
 * page: the submission status box requires mod/assign:viewownsubmissionsummary,
 * which only students hold. These callbacks put the brief where everybody sees
 * it —students included— and in the same place for all of them.
 *
 * @package    assignsubmission_tipnc
 * @copyright  2026 3iPunt (contacte@tresipunt.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /** @var string In the activity header, next to the activity description. */
    public const PLACE_HEADER = 'header';

    /** @var string At the end of the main region, which every theme has. */
    public const PLACE_MAIN = 'main';

    /** @var string Wherever the site says: the browser moves it there. */
    public const PLACE_SELECTOR = 'selector';

    /** @var string Leave the width the theme gives the page. */
    public const WIDTH_THEME = '0';

    /** @var string As wide as the window allows. */
    public const WIDTH_FULL = 'full';

    /**
     * Puts the brief in the activity header, with the activity description.
     *
     * This hook is the only window into the header: it fires at the start of
     * core_renderer::header(), after mod_assign has set the description and
     * before the theme layout exports it.
     *
     * @param  before_http_headers $hook The hook, unused: the brief goes into the
     *                                   activity header, not into the response.
     * @return void
     * @throws dml_exception If the configuration cannot be read.
     */
    public static function add_enunciate_to_activity_header(before_http_headers $hook): void {
        global $PAGE;

        if (self::placement() !== self::PLACE_HEADER) {
            return;
        }

        $html = self::enunciate_html();
        if ($html === null) {
            return;
        }

        $header = $PAGE->activityheader;
        $description = $header->export_for_template($PAGE->get_renderer('core'))['description'] ?? null;
        if ($description === null) {
            // El tema no pinta cabecera de actividad en esta página.
            return;
        }

        $header->set_description($description . $html);
    }

    /**
     * Puts the brief at the end of the main region, or wherever the site says.
     *
     * @param  after_standard_main_region_html_generation $hook The hook.
     * @return void
     * @throws dml_exception If the configuration cannot be read.
     */
    public static function add_enunciate_to_main_region(
        after_standard_main_region_html_generation $hook
    ): void {
        global $PAGE;

        $placement = self::placement();
        if ($placement === self::PLACE_HEADER) {
            return;
        }

        $html = self::enunciate_html();
        if ($html === null) {
            return;
        }

        // El destino a medida se resuelve en el navegador: es el único que puede
        // saber qué ha pintado el tema.
        if ($placement === self::PLACE_SELECTOR) {
            $selector = trim((string) get_config('assignsubmission_tipnc', 'placementselector'));
            if ($selector !== '') {
                $PAGE->requires->js_call_amd(
                    'assignsubmission_tipnc/placement',
                    'init',
                    [$selector]
                );
            }
        }

        $hook->add_html($html);
    }

    /**
     * Widens the assignment page, so the document has room to be read.
     *
     * The width is a site value, so the rule is written here: styles.css cannot
     * take a parameter. It is scoped to this page and to assignments that use
     * this plugin, and it only exists when the site asks for it.
     *
     * @param  before_standard_top_of_body_html_generation $hook The hook.
     * @return void
     * @throws dml_exception If the configuration cannot be read.
     */
    public static function widen_assign_page(
        before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE;

        $width = self::page_width();

        // Todas las páginas de la tarea, no solo la primera: el documento también
        // se edita y se corrige, y el ancho de lectura del tema lo aprieta igual.
        if ($width === null || !str_starts_with($PAGE->pagetype, 'mod-assign-')) {
            return;
        }

        $cm = $PAGE->cm;
        if (!$cm instanceof cm_info || !assign::is_submission_nextcloud($cm)) {
            return;
        }

        $hook->add_html(html_writer::tag('style',
            '@media (min-width: 768px) {'
            . 'body[id^="page-mod-assign-"].pagelayout-standard #page.drawers .main-inner,'
            . 'body[id^="page-mod-assign-"].limitedwidth #page.drawers .main-inner'
            . '{max-width:' . $width . '}'
            . '}'));
    }

    /**
     * Warns, before deleting a submission, that a document goes with it.
     *
     * The core asks «are you sure?» about the submission; what it cannot know is
     * that this site also deletes the document in NextCloud. Better said here
     * than discovered afterwards.
     *
     * @param  before_standard_top_of_body_html_generation $hook The hook.
     * @return void
     * @throws dml_exception If the configuration cannot be read.
     * @throws coding_exception If a language string is missing.
     */
    public static function warn_before_removing_submission(
        before_standard_top_of_body_html_generation $hook
    ): void {
        global $OUTPUT, $PAGE;

        if ($PAGE->pagetype !== 'mod-assign-removesubmissionconfirm') {
            return;
        }

        $cm = $PAGE->cm;
        if (!$cm instanceof cm_info || !assign::is_submission_nextcloud($cm)) {
            return;
        }

        $policy = cleanup::submission_policy();
        if ($policy === cleanup::KEEP) {
            return;
        }

        $message = $policy === cleanup::DROP_ALL
            ? get_string('delete_warning_all', 'assignsubmission_tipnc')
            : get_string('delete_warning_frozen', 'assignsubmission_tipnc');

        $hook->add_html($OUTPUT->notification($message, \core\output\notification::NOTIFY_WARNING));
    }

    /**
     * Asks the student to confirm the document is saved before submitting.
     *
     * The office editor writes to NextCloud in its own time, so submitting right
     * after typing freezes the previous version. Nothing on this side can detect
     * that, so what this does is stop the submission being one click away: it is
     * a confirmation, not a check, and it goes away when the plugin embeds the
     * editor itself and can force the save.
     *
     * @param  before_standard_top_of_body_html_generation $hook The hook.
     * @return void
     * @throws dml_exception If the configuration cannot be read.
     * @throws coding_exception If a language string is missing.
     */
    public static function confirm_document_saved(
        before_standard_top_of_body_html_generation $hook
    ): void {
        global $DB, $OUTPUT, $PAGE;

        if (!in_array($PAGE->pagetype, ['mod-assign-submit', 'mod-assign-editsubmission'], true)) {
            return;
        }

        if (!get_config('assignsubmission_tipnc', 'confirmsaved')) {
            return;
        }

        $cm = $PAGE->cm;
        if (!$cm instanceof cm_info || !assign::is_submission_nextcloud($cm)) {
            return;
        }

        // Sin borradores, «Guardar cambios» del formulario entrega directamente: es
        // el último momento en el que alguien puede decir que su documento está
        // guardado, así que la confirmación tiene que estar ahí y no en una pantalla
        // de confirmación que en esa configuración no llega a existir.
        $drafts = (int) $DB->get_field('assign', 'submissiondrafts', ['id' => $cm->instance], IGNORE_MISSING);
        if ($PAGE->pagetype === 'mod-assign-editsubmission' && $drafts) {
            return;
        }

        // Cómo se vuelca el documento depende de quién manda en el editor: con el
        // editor embebido, el guardado forzado lo pone el plugin y hay botón; con
        // la página de NextCloud depende de su configuración, y no lo sabemos.
        $hook->add_html($OUTPUT->render_from_template(
            'assignsubmission_tipnc/confirm_save',
            [
                'alsosubmits' => $PAGE->pagetype === 'mod-assign-editsubmission',
                'cansave' => viewmode::current() === viewmode::EDITOR,
            ]
        ));
        $PAGE->requires->js_call_amd('assignsubmission_tipnc/confirmsave', 'init');
    }

    /**
     * Maximum width the site wants for the assignment page.
     *
     * @return string|null A CSS length, or null to leave the theme alone.
     * @throws dml_exception If the configuration cannot be read.
     */
    private static function page_width(): ?string {
        $width = (string) get_config('assignsubmission_tipnc', 'pagewidth');

        if ($width === self::WIDTH_FULL) {
            return 'none';
        }

        return (int) $width > 0 ? (int) $width . 'px' : null;
    }

    /**
     * Where the site wants the brief.
     *
     * @return string One of the PLACE_* constants.
     * @throws dml_exception If the configuration cannot be read.
     */
    private static function placement(): string {
        $placement = (string) get_config('assignsubmission_tipnc', 'placement');

        return in_array($placement, [self::PLACE_HEADER, self::PLACE_MAIN, self::PLACE_SELECTOR], true)
            ? $placement
            : self::PLACE_HEADER;
    }

    /**
     * The brief of the assignment being viewed, for anyone who reaches the page.
     *
     * @return string|null The html, or null when this page carries no brief.
     * @throws dml_exception If the configuration cannot be read.
     */
    private static function enunciate_html(): ?string {
        global $PAGE, $USER;

        if ($PAGE->pagetype !== 'mod-assign-view') {
            return null;
        }

        // El módulo se lee a una variable: moodle_page tiene __get pero no
        // __isset, así que empty($PAGE->cm) es cierto aunque el cm esté puesto.
        $cm = $PAGE->cm;
        if (!$cm instanceof cm_info || !assign::is_submission_nextcloud($cm)) {
            return null;
        }

        $instance = (int) $cm->instance;
        $output = $PAGE->get_renderer('assignsubmission_tipnc');

        // Antes que nada: montar el editor contra un servicio que no responde deja
        // que sea el editor quien dé la cara, con un «error de descarga» que no
        // dice a quién avisar ni si el trabajo sigue estando.
        $state = health::state();
        if ($state !== null) {
            $candiagnose = has_capability('assignsubmission/tipnc:view_errors', $cm->context);

            return $output->render(new unavailable(
                (int) ($state->since ?? 0),
                $candiagnose,
                $candiagnose ? new moodle_url('/mod/assign/submission/tipnc/view_errors.php') : null
            ));
        }

        $model = new documents();

        // Entrar en la tarea es el momento en que se sabe quién la imparte y quién
        // la cursa, así que es cuando se reparte el acceso al enunciado: escritura
        // a quien califica, lectura a quien no. Cuesta una llamada la primera vez
        // de cada persona y ninguna después.
        $cangrade = has_capability('mod/assign:grade', $cm->context);

        if ($model->get_enunciate($instance)) {
            (new nextcloud($instance))->grant_enunciate($USER, $cangrade);
        }

        $own = $model->own_work($instance, (int) $USER->id);

        // Quien ya tiene borrador o entrega trabaja sobre su documento: ese es el
        // que se ve, y el enunciado queda arriba como referencia con enlace.
        $embed = viewmode::embeds();

        // Sin enunciado la tarea no funciona, y hasta ahora la única salida era
        // entrar en NextCloud. Quien califica puede pedir que se cree desde aquí.
        $prepare = $cangrade
            ? new moodle_url('/mod/assign/submission/tipnc/prepare.php',
                ['id' => $cm->id, 'sesskey' => sesskey()])
            : null;

        $html = $output->render(enunciate_block::for_assignment(
            $instance, $own === null && $embed, $prepare, $cangrade));

        if ($own !== null && $own->path !== '') {
            $path = $own->path;

            // La entrega congelada se lee, el borrador se escribe: es lo que decide
            // si el editor abre en modo edición o solo lectura.
            $viewer = $embed ? document_viewer::for_document(
                $instance,
                $path,
                $own->ncid,
                basename($path),
                $USER,
                $own->mode !== document::MODE_SUBMISSION,
                $model->frame_height_class(),

                // Congelado, no «no te toca»: entregar es lo que lo cerró.
                $own->mode === document::MODE_SUBMISSION ? document_viewer::READONLY_FROZEN : ''
            ) : null;

            $html .= $output->render(new submission_summary(
                $model->view_url($own->ncid),
                $own->mode,
                $embed,
                $own->timemodified,
                basename($path),
                true,
                $model->frame_height_class(),
                $viewer
            ));
        }

        return $html;
    }
}
