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
 * This file contains the definition for the library class for file submission plugin
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignsubmission_tipnc\api\document;
use assignsubmission_tipnc\api\nextcloud;
use assignsubmission_tipnc\api\error as error_log;
use assignsubmission_tipnc\editor\viewmode;
use assignsubmission_tipnc\log\code;
use assignsubmission_tipnc\models\cleanup;
use assignsubmission_tipnc\models\documents;
use assignsubmission_tipnc\models\health;
use assignsubmission_tipnc\output\document_viewer;
use assignsubmission_tipnc\output\submission_summary;
use assignsubmission_tipnc\output\unavailable;
use assignsubmission_tipnc\task\create_enunciate;
use assignsubmission_tipnc\tipnc;
use assignsubmission_tipnc\tipnc_enun;
use assignsubmission_tipnc\tipnc_error;
use assignsubmission_tipnc\tipnc_open;

/**
 * Library class for file submission plugin extending submission plugin base class
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_tipnc extends assign_submission_plugin {

    /**
     * Get the name of the file submission plugin
     * @return string
     * @throws coding_exception
     */
    public function get_name(): string {
        return get_string('pluginname', 'assignsubmission_tipnc');
    }

    /**
     * Get the default setting for file submission plugin
     *
     * @param MoodleQuickForm $mform
     */
    public function get_settings(MoodleQuickForm $mform) {

    }

    /**
     * Makes sure the assignment has a brief, whenever this plugin is turned on.
     *
     * The core only calls this for plugins that are enabled, so it is the exact
     * moment the assignment starts needing a document —whether it is being
     * created or the plugin is being switched on years later, which used to leave
     * the assignment permanently broken.
     *
     * @param  stdClass $data Settings of the assignment form.
     * @return bool Always true: the brief is prepared out of band.
     * @throws dml_exception If the tables cannot be read.
     */
    public function save_settings(stdClass $data): bool {
        global $USER;

        $assignment = (int) $this->assignment->get_instance()->id;

        if ($assignment !== 0 && !tipnc_enun::get($assignment)) {
            create_enunciate::queue($assignment, (int) $USER->id);
        }

        return true;
    }

    /**
     * Add elements to submission form [ OPEN ]
     *
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool
     * @throws coding_exception|dml_exception
     * @throws moodle_exception
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data): bool {
        global $PAGE, $USER;

        // Sin servicio no hay documento que abrir, y abrir el formulario crearía
        // uno: mejor decirlo que dejar un hueco donde debería estar el trabajo.
        $notice = $this->unavailable_notice($submission);
        if ($notice !== '') {
            $mform->addElement('html', $notice);

            return true;
        }

        $tipncenun = tipnc_enun::get($submission->assignment);
        if (!empty($tipncenun->ncid)) {
            $tipncopen = tipnc_open::get($submission->id);
            if ($tipncopen) {
                if (empty($tipncopen->ncid)) {
                    tipnc_error::log(
                        'get_form_elements',
                        new error_log('1000', 'The NextCloud ID could not be retrieved'),
                        $submission->assignment, $submission->id);
                    return false;
                } else {
                    $ncid = $tipncopen->ncid;
                }
            } else {
                $nextcloud = new nextcloud($submission->assignment);
                $response = $nextcloud->student_open($submission);
                if ($response->success) {
                    $ncid = $response->data;
                } else {
                    return false;
                }
            }
            $urlopen = document::get_url($ncid);
            $output = $PAGE->get_renderer('assignsubmission_tipnc');
            $model = new documents();

            // Es el sitio donde el alumno escribe: mismo visor que en la página
            // de la tarea, para que no parezcan dos documentos distintos.
            $openpath = (new document($submission->assignment))->open_path($submission);

            // Es donde el alumno escribe: el editor abre en modo edición.
            $viewer = document_viewer::for_document(
                (int) $submission->assignment,
                $openpath,
                (int) $ncid,
                basename($openpath),
                $USER,
                true,
                $model->frame_height_class()
            );

            $render = $output->render(new submission_summary(
                $urlopen,
                document::MODE_OPEN,
                true,
                (int) ($submission->timemodified ?? 0),
                basename($openpath),
                true,
                $model->frame_height_class(),
                $viewer
            ));
            // Como html y no como elemento estático: un elemento de formulario
            // reserva la columna de la etiqueta, y aquí no hay etiqueta que poner.
            $mform->addElement('html', $render);
            return true;
        } else {
            if (isset($data->files_filemanager)) {
                return true;
            } else {
                tipnc_error::log(
                    'get_form_elements',
                    new error_log('1001', 'The Enunciate does not exist'),
                    $submission->assignment, $submission->id);
                return false;
            }

        }
    }

    /**
     * Save the files and trigger plagiarism plugin, if enabled,
     * to scan the uploaded files via events trigger
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function save(stdClass $submission, stdClass $data): bool {
        $tipncenun = tipnc_enun::get($submission->assignment);
        if (!$tipncenun) {
            if (isset($data->files_filemanager)) {
                return true;
            } else {
                tipnc_error::log(
                    'save',
                    new error_log('1100', 'The Enunciate does not exist'),
                    $submission->assignment, $submission->id);
                return false;
            }
        }
        $tipncopen = tipnc_open::get($submission->id);
        if (!$tipncopen) {
            if (isset($data->files_filemanager)) {
                return true;
            } else {
                tipnc_error::log(
                    'save',
                    new error_log('1101', 'The Open Submission does not exist'),
                    $submission->assignment, $submission->id);
                return false;
            }
        }
        // Guardar es guardar el borrador. Congelar la entrega es otra cosa y
        // ocurre en otro momento —submit_for_grading()—, aunque sin borradores
        // el core los encadene y parezcan el mismo clic.
        if ($submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            return $this->freeze($submission);
        }

        return true;
    }

    /**
     * Freezes the submission when the work is handed in for marking.
     *
     * With «Require students to click the submit button» —the usual setting— the
     * core never passes SUBMITTED to save(): it calls this instead. Without it,
     * students clicked submit, Moodle marked the work as handed in, and in
     * NextCloud nothing at all happened.
     *
     * @param  stdClass $submission The submission being handed in.
     * @return bool True when the copy was frozen.
     * @throws dml_exception If the incident cannot be recorded.
     * @throws moodle_exception If the assignment cannot be read.
     */
    public function submit_for_grading($submission): bool {
        return $this->freeze($submission);
    }

    /**
     * Copies the draft into the submission that gets marked.
     *
     * @param  stdClass $submission The submission being handed in.
     * @return bool True when the copy was frozen.
     * @throws dml_exception If the incident cannot be recorded.
     * @throws moodle_exception If the assignment cannot be read.
     */
    private function freeze(stdClass $submission): bool {
        $enunciate = tipnc_enun::get($submission->assignment);

        if (!$enunciate) {
            tipnc_error::log('freeze',
                new error_log('1100', 'The Enunciate does not exist'),
                $submission->assignment, $submission->id);

            return false;
        }

        if (!tipnc_open::get($submission->id)) {
            tipnc_error::log('freeze',
                new error_log('1101', 'The Open Submission does not exist'),
                $submission->assignment, $submission->id);

            return false;
        }

        $response = (new nextcloud($submission->assignment))->student_submit($submission);

        if (!$response->success) {
            tipnc_error::log('freeze', $response->error, $submission->assignment, $submission->id);

            return false;
        }

        // Puede haber salido bien y traer un aviso: la copia está hecha, pero a
        // alguien no se le pudo dar acceso. Se registra y la entrega sigue siendo
        // válida, porque lo que se califica ya existe.
        //
        // Una respuesta sin problemas trae un error de relleno con código «0», que
        // no es el «operación correcta» del catálogo: hay que descartar los dos.
        if (!in_array((string) $response->error->code, ['', '0', code::OK], true)) {
            tipnc_error::log('freeze:share', $response->error,
                $submission->assignment, $submission->id);
        }

        return true;
    }

    /**
     * Remove files from this submission.
     *
     * @param stdClass $submission
     * @return bool
     * @throws moodle_exception
     */
    public function remove(stdClass $submission): bool {
        $submissionid = $submission ? $submission->id : 0;
        if ($submissionid) {
            try {
                // Primero NextCloud: las filas que se borran a continuación son
                // las que dicen qué documentos eran de esta entrega.
                (new cleanup())->on_submission_removed((int) $submission->assignment, $submission);
                tipnc::delete_by_submissionid($submission->id, $submission->assignment);
                tipnc_open::delete_by_submissionid($submission->id, $submission->assignment);
            } catch (moodle_exception $e) {
                tipnc_error::log(
                    'remove',
                    new error_log('1200', $e->getMessage()),
                    $submission->assignment, $submission->id);
                return false;
            }
        }
        return true;
    }

    /**
     * Produce a list of files suitable for export that represent this feedback or submission
     *
     * @param stdClass $submission The submission
     * @param stdClass $user The user record - unused
     * @return array - return an array of files indexed by filename
     */
    public function get_files(stdClass $submission, stdClass $user): array {
        return [];
    }

    /**
     * Display the list of files in the submission status table
     *
     * @param stdClass $submission
     * @param bool $showviewlink Set this to true if the list of files is long
     * @return string
     * @throws dml_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function view_summary(stdClass $submission, & $showviewlink): string {
        global $PAGE, $USER;

        // Lo primero, porque si el servicio no responde nada de lo de abajo va a
        // poder decir la verdad sobre el documento.
        $notice = $this->unavailable_notice($submission);
        if ($notice !== '') {
            return $notice;
        }

        $tipncenun = tipnc_enun::get($submission->assignment);
        if (!$tipncenun) {
            tipnc_error::log(
                'view_summary',
                new error_log('1300', 'The Enunciate does not exist. ' .
                'Check if the task also has the delivery as a file activated'),
                $submission->assignment, $submission->id);
            return '';
        }
        $isteacher = \assignsubmission_tipnc\assign::is_teacher($submission->assignment);

        // La tabla de calificación monta el registro de entrega a mano y no le pone
        // estado (gradingtable.php), así que aquí se resuelve contra la entrega real.
        $status = $submission->status
            ?? \assignsubmission_tipnc\assign::get_submission($submission->id)->status
            ?? null;

        if ($isteacher) {
            if (!is_null($status)) {
                switch ($status) {
                    case ASSIGN_SUBMISSION_STATUS_DRAFT:
                    case ASSIGN_SUBMISSION_STATUS_REOPENED:
                    case ASSIGN_SUBMISSION_STATUS_NEW:
                        // Lo que le interesa a quien corrige es en qué anda esta
                        // persona, no volver a ver el enunciado: si ya tiene
                        // borrador, es lo que está escribiendo.
                        $tipncopen = tipnc_open::get($submission->id);
                        if (!empty($tipncopen->ncid)) {
                            $mode = document::MODE_OPEN;
                            $ncid = $tipncopen->ncid;
                            break;
                        }

                        // Sin borrador no ha empezado. Se dice, en lugar de enseñar
                        // el enunciado, que parece que entregó el documento en blanco.
                        return $PAGE->get_renderer('assignsubmission_tipnc')->render(
                            new unavailable(0, false, null, unavailable::NOT_STARTED));
                    case ASSIGN_SUBMISSION_STATUS_SUBMITTED:
                        $mode = document::MODE_SUBMISSION;
                        $tipncsub = tipnc::get($submission->id);
                        $ncid = $tipncsub->ncid;
                        break;
                    default:
                        tipnc_error::log(
                            'view_summary',
                            new error_log('1301', 'Assign status unknown'),
                            $submission->assignment, $submission->id);
                        return '';
                }
            }
        } else {
            if (!is_null($status)) {
                switch ($status) {
                    case ASSIGN_SUBMISSION_STATUS_DRAFT:
                    case ASSIGN_SUBMISSION_STATUS_REOPENED:
                        // Pintar una caja de estado no puede crear un documento:
                        // copiarlo, compartirlo y localizarlo son tres peticiones
                        // con treinta segundos de espera cada una, y esto se pinta
                        // en cada visita. El borrador nace cuando se abre el
                        // formulario, que es cuando alguien lo ha pedido.
                        $tipncopen = tipnc_open::get($submission->id);

                        if (empty($tipncopen->ncid)) {
                            $mode = document::MODE_ENUN;
                            $ncid = $tipncenun->ncid;
                            break;
                        }

                        $mode = document::MODE_OPEN;
                        $ncid = $tipncopen->ncid;
                        break;
                    case ASSIGN_SUBMISSION_STATUS_NEW:
                        // Si ya tiene borrador, es lo que está trabajando, diga lo
                        // que diga el estado: el documento se crea al abrir el
                        // formulario y el estado no cambia hasta que guarda.
                        $tipncopen = tipnc_open::get($submission->id);
                        if (!empty($tipncopen->ncid)) {
                            $mode = document::MODE_OPEN;
                            $ncid = $tipncopen->ncid;
                            break;
                        }

                        // El acceso al enunciado se reparte al entrar en la tarea
                        // y se recuerda; pedirlo aquí era una llamada por fila de
                        // la tabla de calificación.
                        $mode = document::MODE_ENUN;
                        $ncid = $tipncenun->ncid;
                        break;
                    case ASSIGN_SUBMISSION_STATUS_SUBMITTED:
                        $mode = document::MODE_SUBMISSION;
                        $tipncsub = tipnc::get($submission->id);
                        $ncid = $tipncsub->ncid;
                        break;
                    default:
                        tipnc_error::log(
                            'view_summary',
                            new error_log('1302', 'Assign status unknown'),
                            $submission->assignment, $submission->id);
                        return '';
                }
            }
        }

        // Moodle dice que hay entrega y en NextCloud no hay documento. Pasa cuando
        // el congelado falló a medias, y dejarlo en blanco hacía que se pareciera
        // a «aún no ha entregado», que es lo contrario de lo que ocurre.
        if (empty($ncid)) {
            tipnc_error::log(
                'view_summary',
                new error_log('1303', 'The NextCloud ID could not be retrieved'),
                $submission->assignment, $submission->id);

            $candiagnose = has_capability('assignsubmission/tipnc:view_errors',
                $this->assignment->get_context());

            return $PAGE->get_renderer('assignsubmission_tipnc')->render(new unavailable(
                0,
                $candiagnose,
                $candiagnose ? new moodle_url('/mod/assign/submission/tipnc/view_errors.php') : null,
                unavailable::NO_DOCUMENT
            ));
        }

        $url = document::get_url($ncid);
        $output = $PAGE->get_renderer('assignsubmission_tipnc');
        $own = (int) ($submission->userid ?? 0) === (int) $USER->id;

        // El nombre del documento solo se pone cuando sale gratis: en la tabla de
        // calificación habría que consultarlo por cada fila.
        $filename = null;
        if ($own) {
            $document = new document($submission->assignment);
            $filename = basename(match ($mode) {
                document::MODE_OPEN => $document->open_path($submission),
                document::MODE_SUBMISSION => $document->submission_path($submission),
                default => $document->get_enunciate(),
            });
        }

        // Al corregir, el documento se abre donde se está poniendo la nota: salir a
        // NextCloud para leerlo parte la corrección en dos pantallas.
        [$viewer, $marking] = $this->marking_viewer($submission, $mode, (int) $ncid);

        return $output->render(new submission_summary(
            $url,
            $mode,
            $viewer !== null,
            (int) ($submission->timemodified ?? 0),
            $marking ?? $filename,
            $own,
            (new documents())->frame_height_class(),
            $viewer
        ));
    }

    /**
     * The submitted document, opened where it is being marked.
     *
     * Only in the grading panel. The grading table calls view_summary() once per
     * row, and one editor per row would be a page full of editors.
     *
     * @param  stdClass $submission The submission being looked at.
     * @param  string   $mode       Which of the documents it is.
     * @param  int      $ncid       Identifier of the document in NextCloud.
     * @return array The viewer and the file name, both null when nothing is opened.
     * @throws dml_exception If the configuration cannot be read.
     * @throws moodle_exception If the editor is chosen and has no secret.
     */
    private function marking_viewer(stdClass $submission, string $mode, int $ncid): array {
        global $PAGE, $USER;

        // El core pone el tipo de página con la acción, y el panel de corrección se
        // pinta por fragmento con la suya: es lo que distingue esto de la tabla.
        if ($PAGE->pagetype !== 'mod-assign-gradingpanel' || $mode !== document::MODE_SUBMISSION) {
            return [null, null];
        }

        // Sin servicio no hay documento que abrir: montar el editor solo consigue
        // que sea él quien dé un error que no explica nada.
        if (health::is_down()) {
            return [null, null];
        }

        // Con evaluación anónima el nombre del archivo lleva dentro el del alumnado,
        // así que ni se abre aquí ni se da acceso: en NextCloud se vería quién es.
        if ($this->assignment->is_blind_marking()) {
            return [null, null];
        }

        $student = core_user::get_user((int) $submission->userid);
        if (!$student) {
            return [null, null];
        }

        // Haber llegado a esta pantalla ya es la comprobación: el core exige
        // mod/assign:grade para pintarla. Aquí solo se aplica lo que Moodle decidió.
        // Va antes de mirar cómo se muestra el documento, porque quien lo abre en
        // otra pestaña necesita el acceso igual que quien lo ve incrustado.
        (new nextcloud((int) $submission->assignment))->grant_submission($USER, $submission);

        if (!viewmode::embeds()) {
            return [null, null];
        }

        $path = (new document((int) $submission->assignment))->submission_path($submission);

        // El profesorado tiene permiso de escritura sobre la entrega desde que se
        // congela: corregir sobre el documento es lo que ese permiso existe para.
        return [
            document_viewer::for_document(
                (int) $submission->assignment,
                $path,
                $ncid,
                basename($path),
                $USER,
                true,
                (new documents())->frame_height_class()
            ),
            basename($path),
        ];
    }

    /**
     * The full view of a submission, which this plugin does not have.
     *
     * Everything there is to show —the document, its state and where to open it—
     * already fits in the summary, so nothing ever sets $showviewlink and the core
     * never reaches this. It stays because it is part of the contract.
     *
     * @param  stdClass $submission The submission, unused.
     * @return string Always empty.
     */
    public function view(stdClass $submission): string {
        return '';
    }

    /**
     * Return true if this plugin can upgrade an old Moodle 2.2 assignment of this type
     * and version.
     *
     * @param string $type
     * @param int $version
     * @return bool True if upgrade is possible
     */
    public function can_upgrade($type, $version): bool {
        return false;
    }


    /**
     * Upgrade the settings from the old assignment
     * to the new plugin based one
     *
     * @param context $oldcontext - the old assignment context
     * @param stdClass $oldassignment - the old assignment data record
     * @param string $log record log events here
     * @return bool Was it a success? (false will trigger rollback)
     */
    public function upgrade_settings(context $oldcontext, stdClass $oldassignment, & $log): bool {
        return true;
    }

    /**
     * Upgrade the submission from the old assignment to the new one
     *
     * @param context $oldcontext The context of the old assignment
     * @param stdClass $oldassignment The data record for the old oldassignment
     * @param stdClass $oldsubmission The data record for the old submission
     * @param stdClass $submission The data record for the new submission
     * @param string $log Record upgrade messages in the log
     * @return bool true or false - false will trigger a rollback
     */
    public function upgrade(context $oldcontext, stdClass $oldassignment,
                            stdClass $oldsubmission, stdClass $submission, &$log): bool {
        return true;
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     * @throws moodle_exception
     */
    public function delete_instance(): bool {
        $instance = (int) $this->assignment->get_instance()->id;

        try {
            (new cleanup())->on_assignment_deleted($instance);
            tipnc::delete($this->assignment->get_instance()->id);
            tipnc_enun::delete($this->assignment->get_instance()->id);
            tipnc_open::delete($this->assignment->get_instance()->id);
            return true;
        } catch (moodle_exception $e) {
            tipnc_error::log(
                'delete_instance',
                new error_log('1400', $e->getMessage()),
                $this->assignment->get_instance()->id);
            return false;
        }
    }

    /**
     * Formatting for log info
     *
     * @param stdClass $submission The submission
     * @return string
     */
    public function format_for_log(stdClass $submission): string {
        return 'format_log';
    }

    /**
     * Stops the submission while the documents cannot be reached.
     *
     * The core shows what this returns on the confirmation screen and refuses to
     * go on. Letting it through would freeze a copy of a document nobody could
     * read: the student would be told they had handed in work that is not there.
     *
     * @param  stdClass $submission The submission about to be handed in.
     * @return bool|string True to allow it, or why it cannot be done now.
     * @throws coding_exception If a language string is missing.
     */
    public function precheck_submission($submission) {
        if (health::is_down()) {
            return get_string('unavailable_submit', 'assignsubmission_tipnc');
        }

        return true;
    }

    /**
     * What is said instead of a document that cannot be reached.
     *
     * @return string The notice, empty when everything is answering.
     * @throws coding_exception If a language string is missing.
     * @throws moodle_exception If the renderer cannot be built.
     */
    private function unavailable_notice(?stdClass $submission = null): string {
        global $PAGE;

        // Quien no existe en NextCloud no puede recibir su documento ni escribir
        // en él: es un impedimento suyo y permanente, no una caída pasajera, y
        // hasta que alguien le cree la cuenta no hay nada que pueda hacer.
        if ($submission !== null && $this->owner_missing($submission)) {
            return $PAGE->get_renderer('assignsubmission_tipnc')->render(
                new unavailable(0, false, null, unavailable::NO_ACCOUNT));
        }

        $state = health::state();

        if ($state === null) {
            return '';
        }

        $candiagnose = has_capability('assignsubmission/tipnc:view_errors', $this->assignment->get_context());

        return $PAGE->get_renderer('assignsubmission_tipnc')->render(new unavailable(
            (int) ($state->since ?? 0),
            $candiagnose,
            $candiagnose ? new moodle_url('/mod/assign/submission/tipnc/view_errors.php') : null
        ));
    }

    /**
     * Whether the person a submission belongs to has no account in NextCloud.
     *
     * It is answered from what already happened —the incident that says their
     * share was rejected— and not by asking NextCloud on every page: the answer
     * is the same until somebody creates the account.
     *
     * @param  stdClass $submission The submission.
     * @return bool True when their share was rejected for not existing.
     * @throws dml_exception If the log cannot be read.
     */
    private function owner_missing(stdClass $submission): bool {
        global $DB;

        $userid = (int) ($submission->userid ?? 0);

        if ($userid === 0 || empty($submission->id)) {
            return false;
        }

        // A quién afectaba, no en qué entrega ocurrió: el rechazo puede haber sido
        // de otra persona —del profesorado, por ejemplo— y entonces no dice nada
        // de quien entregó.
        return $DB->record_exists('assignsubmission_tipnc_log', [
            'errorcode' => code::SHARE_NO_ACCOUNT,
            'submission' => $submission->id,
            'affecteduserid' => $userid,
        ]);
    }

    /**
     * Closes the document when the submission is locked.
     *
     * @param  stdClass $submission The submission being locked.
     * @param  stdClass $flags      Flags of the submission, unused.
     * @return void
     * @throws dml_exception If the incident cannot be recorded.
     */
    public function lock($submission, stdClass $flags): void {
        if (!empty($submission->id)) {
            (new nextcloud($submission->assignment))->set_draft_access($submission, false);
        }
    }

    /**
     * Opens the document again when the submission is unlocked.
     *
     * @param  stdClass $submission The submission being unlocked.
     * @param  stdClass $flags      Flags of the submission, unused.
     * @return void
     * @throws dml_exception If the incident cannot be recorded.
     */
    public function unlock($submission, stdClass $flags): void {
        if (!empty($submission->id)) {
            (new nextcloud($submission->assignment))->set_draft_access($submission, true);
        }
    }

    /**
     * Gives the document back when the submission returns to draft.
     *
     * Sending it back to draft is telling somebody to carry on working: if the
     * document stays read only, that invitation is empty.
     *
     * @param  stdClass $submission The submission going back to draft.
     * @return void
     * @throws dml_exception If the incident cannot be recorded.
     */
    public function revert_to_draft(stdClass $submission): void {
        if (!empty($submission->id)) {
            (new nextcloud($submission->assignment))->set_draft_access($submission, true);
        }
    }

    /**
     * Whether there is nothing in this submission.
     *
     * What this plugin contributes is a document, so it is empty exactly when no
     * document has been registered for that submission —neither draft nor frozen
     * copy—. Answering «never empty», as it used to, made the core believe every
     * student had handed something in, including those who never opened it.
     *
     * @param  stdClass $submission The submission to look at.
     * @return bool True when there is no document.
     * @throws dml_exception If the tables cannot be read.
     */
    public function is_empty(stdClass $submission): bool {
        $id = (int) ($submission->id ?? 0);

        if ($id === 0) {
            return true;
        }

        return !tipnc::get($id) && !tipnc_open::get($id);
    }

    /**
     * Whether what is about to be saved is empty.
     *
     * The core refuses the submission when every plugin says yes, so this only
     * says yes when it is sure: no submission on record means nobody ever opened
     * the document, and there is nothing to hand in.
     *
     * @param  stdClass $data The submission data of the form.
     * @return bool True when there is nothing to submit.
     * @throws dml_exception If the tables cannot be read.
     */
    public function submission_is_empty(stdClass $data): bool {
        global $USER;

        $userid = (int) ($data->userid ?? 0) ?: (int) $USER->id;
        $submission = $this->assignment->get_user_submission($userid, false);

        return !$submission || $this->is_empty($submission);
    }

    /**
     * Starts a reopened attempt from the work of the previous one.
     *
     * The core offers «base the new attempt on the last submission» and this is
     * what makes that true. Answering yes without copying anything, as it used
     * to, sent the student back to a blank brief.
     *
     * @param  stdClass $sourcesubmission The attempt being carried over.
     * @param  stdClass $destsubmission   The attempt starting now.
     * @return bool True when the document is ready.
     * @throws dml_exception If the incident cannot be recorded.
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission): bool {
        $response = (new nextcloud((int) $destsubmission->assignment))
            ->reattempt_from($sourcesubmission, $destsubmission);

        if (!$response->success) {
            tipnc_error::log('copy_submission', $response->error,
                $destsubmission->assignment, $destsubmission->id);

            return false;
        }

        return true;
    }

    /**
     * Determine if the plugin allows image file conversion
     * @return bool
     */
    public function allow_image_conversion(): bool {
        return true;
    }
}
