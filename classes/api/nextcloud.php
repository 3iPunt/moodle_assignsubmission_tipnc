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
 * Class nextcloud
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_tipnc\api;

use cm_info;
use curl;
use dml_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * Class nextcloud
 *
 * @package     assignsubmission_tipnc
 * @copyright   2021 Tresipunt
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nextcloud {

    const FOLDER_TASKS = 'tasks';
    const ORIGINAL_DOC = 'new.docx';
    const PREFIX_ENUN = 'enun_';
    const PREFIX_SUBMISSION = 'task_';
    const TIMEOUT = 30;

    const SHARE_TYPE_USER = 0;
    const PERMISSION_READ = 1;
    const PERMISSION_EDITION = 2;
    const PERMISSION_UPDATE = 4;
    const PERMISSION_ALL = 31;

    /** @var string Host */
    protected $host;

    /** @var string User */
    protected $user;

    /** @var string Password */
    protected $password;

    /**
     * constructor.
     *
     * @throws dml_exception
     */
    public function __construct() {
        $this->host = get_config('assignsubmission_tipnc', 'host');
        $this->user = get_config('assignsubmission_tipnc', 'user');
        $this->password = get_config('assignsubmission_tipnc', 'password');
    }

    /**
     * Copy File.
     *
     * @param string $origin
     * @param string $destiny
     * @return response
     */
    protected function copy_file(string $origin, string $destiny): response {

        $curl = new curl();
        $url = $this->host . '/remote.php/dav/files/' . $this->user . '/' . $origin . '?format=json';
        $destiny_url = $this->host . '/remote.php/dav/files/' . $this->user . '/' . $destiny;
        $headers = array();
        $headers[] = "Content-type: application/json";
        $headers[] = "OCS-APIRequest: true";
        $headers[] = "Destination: " . $destiny_url;
        $curl->setHeader($headers);
        $params = new stdClass();

        try {
            $curl->get($url, json_encode($params), $this->get_options_curl('COPY'));
            $response = $curl->getResponse();

            if ($response['HTTP/1.1'] === '201 Created' || $response['HTTP/1.1'] === '204 No Content') {
                $response = new response(true, '');
            } else {
                $response = new response(false, null, new error(1001, $response['HTTP/1.1']));
            }

        } catch (\Exception $e) {
            $response = new response(false, null,
                new error(1000, $e->getMessage()));
        }

        return $response;
    }

    /**
     * Listing.
     *
     * @param string $file
     * @return response
     */
    protected function listing(string $file): response {
        $url = $this->host . '/remote.php/dav/files/' . $this->user . '/' . $file;
        $headers = array();
        $headers[] = "Content-type: application/xml";
        $headers[] = "OCS-APIRequest: true";
        $headers[] = 'Authorization: Basic '. base64_encode($this->user .':' . $this->password);
        $params = '<d:propfind  xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns" xmlns:nc="http://nextcloud.org/ns">
                    <d:prop>
                        <d:getlastmodified />
                        <d:getetag />
                        <d:getcontenttype />
                        <d:resourcetype />
                        <oc:fileid />
                        <oc:permissions />
                        <oc:size />
                        <d:getcontentlength />
                        <nc:has-preview />
                        <oc:favorite />
                        <oc:comments-unread />
                        <oc:owner-display-name />
                        <oc:share-types />
                    </d:prop>
                   </d:propfind>';

        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'PROPFIND',
                CURLOPT_POSTFIELDS => $params,
                CURLOPT_HTTPHEADER => $headers,
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            $xml = str_replace('d:', '', $response);
            $xml = str_replace('oc:', '', $xml);
            $xml = str_replace('nc:', '', $xml);
            $xml = simplexml_load_string($xml) or die("Something is wrong");

            if (isset($xml->response->propstat->prop->fileid)) {
                $fileid = current($xml->response->propstat->prop->fileid);
                $response = new response(true, $fileid);
            } else {
                $response = new response(
                    false, null, new error(2001, 'No se ha recuperado el File ID'));
            }

        } catch (\Exception $e) {
            $response = new response(false, null,
                new error(2000, $e->getMessage()));
        }

        return $response;
    }

    /**
     * Set Permission.
     *
     * @param string $username
     * @param int $permission
     * @param $file
     * @return response
     */
    protected function set_permission(string $username, int $permission, $file): response {

        $curl = new curl();
        $url = $this->host . '/ocs/v2.php/apps/files_sharing/api/v1/shares?format=json';
        $headers = array();
        $headers[] = "Content-type: application/json";
        $headers[] = "OCS-APIRequest: true";
        $curl->setHeader($headers);
        $params = new stdClass();
        $params->path = $file;
        $params->shareType = self::SHARE_TYPE_USER;
        $params->permissions = $permission;
        $params->shareWith = $username;

        try {
            $res = $curl->post($url, json_encode($params), $this->get_options_curl('POST'));
            $res = json_decode($res, true);
            $response = $curl->getResponse();

            if ($response['HTTP/1.1'] === '200 OK') {
                if (isset($res['ocs']['data']['id'])) {
                    $response = new response(true, $res['ocs']['data']['id']);
                } else {
                    $response = new response(false, null, new error(3002, 'Respuesta no esperada'));
                }
            } else {
                $response = new response(false, null, new error(3001, $response['HTTP/1.1']));
            }

        } catch (\Exception $e) {
            $response = new response(false, null,
                new error(3000, $e->getMessage()));
        }

        return $response;
    }


    /**
     * Delete Permission.
     *
     * @param int $share_id
     * @return response
     */
    protected function delete_permission(int $share_id): response {

        $curl = new curl();
        $url = $this->host . '/ocs/v2.php/apps/files_sharing/api/v1/shares/' . $share_id . '?format=json';
        $headers = array();
        $headers[] = "Content-type: application/json";
        $headers[] = "OCS-APIRequest: true";
        $curl->setHeader($headers);
        $params = new stdClass();
        $params->share_id = $share_id;

        try {
            $res = $curl->post($url, json_encode($params), $this->get_options_curl('DELETE'));
            $res = json_decode($res, true);
            $response = $curl->getResponse();

            if ($response['HTTP/1.1'] === '200 OK') {
                $response = new response(true, '');
            } else {
                $response = new response(false, null, new error(3001, $response['HTTP/1.1']));
            }

        } catch (\Exception $e) {
            $response = new response(false, null,
                new error(3000, $e->getMessage()));
        }

        return $response;
    }


    /**
     * Student Submit.
     *
     * @param cm_info $cm
     * @return bool
     */
    public function teacher_create(cm_info $cm): bool {
        global $USER, $DB;
        $instance = $cm->instance;
        $new = self::FOLDER_TASKS . '/' . self::ORIGINAL_DOC;
        $enun = self::FOLDER_TASKS . '/' . self::PREFIX_ENUN . $instance . '.docx';
        $response_copy = $this->copy_file($new, $enun);
        if ($response_copy->success) {
            $teacherusername = $USER->username;
            // TODO. OVERRIDE!!!!
            //$teacherusername = 'profesor1';
            $response_share = $this->set_permission($this->user, self::PERMISSION_ALL, $enun);
            $response_share = $this->set_permission($teacherusername, self::PERMISSION_ALL, $enun);
            if ($response_share->success) {
                $response_listing = $this->listing($enun);
                if ($response_listing->success) {
                    $data = new stdClass();
                    $data->assignment = $instance;
                    $data->ncid = $response_listing->data;
                    $data->userid = $USER->id;
                    try {
                        $DB->insert_record('assignsubmission_tipnc_enun', $data);
                        return true;
                    } catch (\moodle_exception $e) {
                        return false;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Student Submit.
     *
     * @param stdClass $submission
     * @param int $ncid
     * @return bool
     * @throws dml_exception
     */
    public function student_view_summary(stdClass $submission): bool {
        global $USER;
        $username = $USER->username;
        // TODO. OVERRIDE!!!!
        //$username = 'alumno1';
        $enun = self::FOLDER_TASKS . '/' . self::PREFIX_ENUN . $submission->assignment . '.docx';
        $response = $this->set_permission($username, self::PERMISSION_READ, $enun);
        if ($response->success) {
            return true;
        }

        return false;

    }

    /**
     * Student Submit.
     *
     * @param stdClass $submission
     * @return bool
     * @throws dml_exception
     */
    public function student_open(stdClass $submission): bool {
        global $USER, $DB;
        $instance = $submission->assignment;
        $studentusername = $USER->username;
        // TODO. OVERRIDE!!!!
        //$studentusername = 'alumno1';
        $enun = self::FOLDER_TASKS . '/' . self::PREFIX_ENUN . $instance . '.docx';
        $sub = self::FOLDER_TASKS . '/' . self::PREFIX_SUBMISSION . $instance . '_' . $studentusername . '.docx';
        $response_copy = $this->copy_file($enun, $sub);
        if ($response_copy->success) {
            $response_share = $this->set_permission($this->user, self::PERMISSION_ALL, $sub);
            $response_share = $this->set_permission($studentusername, self::PERMISSION_ALL, $sub);
            if ($response_share->success) {
                $response_listing = $this->listing($sub);
                if ($response_listing->success) {
                    $submissiontipnc = $DB->get_record('assignsubmission_tipnc', array('submission'=>$submission->id));
                    if ($submissiontipnc) {
                        $submissiontipnc->ncid = $response_listing->data;
                        $submissiontipnc->shareid = $response_share->data;
                        try {
                            $DB->update_record('assignsubmission_tipnc', $submissiontipnc);
                            return true;
                        } catch (\moodle_exception $e) {
                            return false;
                        }
                    } else {
                        $submissiontipnc = new stdClass();
                        $submissiontipnc->assignment = $submission->assignment;
                        $submissiontipnc->submission = $submission->id;
                        $submissiontipnc->ncid = $response_listing->data;
                        $submissiontipnc->shareid = $response_share->data;
                        try {
                            $DB->insert_record('assignsubmission_tipnc', $submissiontipnc);
                            return true;
                        } catch (\moodle_exception $e) {
                            return false;
                        }
                    }
                }
            }
        }
        return false;

    }

    /**
     * Student Submit.
     *
     * @param int $shareid
     * @param int $assignment
     * @param int $userid
     * @return bool
     * @throws dml_exception
     */
    public function student_submits(int $shareid, int $assignment, int $userid): bool {
        global $USER;
        $teacher = \core_user::get_user($userid);
        $studentusername = $USER->username;
        $teachername = $teacher->username;
        // TODO. OVERRIDE!!!!
        //$studentusername = 'alumno1';
        //$teachername = 'profesor1';
        $sub = self::FOLDER_TASKS . '/' . self::PREFIX_SUBMISSION . $assignment . '_' . $studentusername . '.docx';
        $response_share = $this->set_permission($teachername, self::PERMISSION_ALL, $sub);
        $response_share = $this->delete_permission($shareid);
        $response_share = $this->set_permission($studentusername, self::PERMISSION_READ, $sub);
        if ($response_share->success) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get Options CURL.
     *
     * @param string $method
     * @return array
     */
    private function get_options_curl(string $method): array {
        return [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
            'CURLOPT_CUSTOMREQUEST' => $method,
            'CURLOPT_SSLVERSION' => CURL_SSLVERSION_TLSv1_2,
            'CURLOPT_USERPWD' => "{$this->user}:{$this->password}",
        ];
    }


}
