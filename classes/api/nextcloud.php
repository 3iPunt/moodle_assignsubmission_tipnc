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

use assignsubmission_tipnc\tipnc;
use assignsubmission_tipnc\tipnc_enun;
use assignsubmission_tipnc\tipnc_error;
use assignsubmission_tipnc\tipnc_open;
use core_user;
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

    const TIMEOUT = 30;

    const SHARE_TYPE_USER = 0;
    const PERMISSION_READ = 1;
    const PERMISSION_EDITION = 2;
    const PERMISSION_UPDATE = 4;
    const PERMISSION_ALL = 31;

    /** @var int Instance */
    protected $instance;

    /** @var string Host */
    protected $host;

    /** @var string User */
    protected $user;

    /** @var string Password */
    protected $password;

    /** @var document Document */
    protected $document;

    /**
     * constructor.
     *
     * @param int $instance
     * @throws dml_exception
     */
    public function __construct(int $instance) {
        $this->host = get_config('assignsubmission_tipnc', 'host');
        $this->user = get_config('assignsubmission_tipnc', 'user');
        $this->password = get_config('assignsubmission_tipnc', 'password');
        $this->instance = $instance;
        $this->document = new document($instance);
    }

    /**
     * Teacher create Assign with NextCloud Submission.
     *
     * @return response
     * @throws dml_exception
     */
    public function teacher_create(): response {
        global $USER;
        $template = $this->document->get_template();
        $enun = $this->document->get_enunciate();
        $res_copy = $this->copy_file($template, $enun);
        if ($res_copy->success) {
            $teacher = $USER->username;
            $res_share_teacher = $this->set_permission($enun, $teacher, self::PERMISSION_ALL);
            if ($res_share_teacher->success) {
                $res_listing = $this->listing($enun);
                if ($res_listing->success) {
                    $data = new stdClass();
                    $data->assignment = $this->instance;
                    $data->ncid = $res_listing->data;
                    $data->userid = $USER->id;
                    tipnc_enun::set($data);
                    return new response(true, $data->ncid);
                } else {
                    tipnc_error::log(
                        'teacher_create:listing', $res_listing->error, $this->instance);
                    return $res_listing;
                }
            } else {
                tipnc_error::log(
                    'teacher_create:set_permission', $res_share_teacher->error, $this->instance);
                return $res_share_teacher;
            }
        } else {
            tipnc_error::log(
                'teacher_create:copy_file', $res_copy->error, $this->instance);
            return $res_copy;
        }
    }

    /**
     * Student View in Summary.
     *
     * @return response
     * @throws dml_exception
     */
    public function student_view_summary(): response {
        global $USER;
        $student = $USER->username;
        $enun = $this->document->get_enunciate();
        $res = $this->set_permission($enun, $student, self::PERMISSION_READ);
        if (!$res->success) {
            tipnc_error::log('student_view_summary:set_permission', $res->error, $this->instance);
        }
        return $res;
    }

    /**
     * Student Open Submission.
     *
     * @param stdClass $submission
     * @return response
     * @throws dml_exception
     */
    public function student_open(stdClass $submission): response {
        global $USER;
        $student = $USER->username;
        $enun = $this->document->get_enunciate();
        $open = $this->document->get_open($student);
        $res_copy = $this->copy_file($enun, $open);
        if ($res_copy->success) {
            $res_share_student = $this->set_permission($open, $student, self::PERMISSION_ALL);
            if ($res_share_student->success) {
                $res_listing = $this->listing($open);
                if ($res_listing->success) {
                    $tipnc_open = tipnc_open::get($submission->id);
                    if ($tipnc_open) {
                        $tipnc_open->ncid = $res_listing->data;
                        tipnc_open::update($tipnc_open);
                        return new response(true, $tipnc_open->ncid);
                    } else {
                        $tipnc_open = new stdClass();
                        $tipnc_open->assignment = $submission->assignment;
                        $tipnc_open->submission = $submission->id;
                        $tipnc_open->ncid = $res_listing->data;
                        tipnc_open::set($tipnc_open);
                        return new response(true, $tipnc_open->ncid);
                    }
                } else {
                    tipnc_error::log(
                        'student_open:listing', $res_listing->error, $this->instance, $submission->id);
                    return $res_listing;
                }
            } else {
                tipnc_error::log(
                    'student_open:set_permission', $res_share_student->error, $this->instance, $submission->id);
                return $res_share_student;
            }
        } else {
            tipnc_error::log(
                'student_open:copy_file', $res_copy->error, $this->instance, $submission->id);
            return $res_copy;
        }
    }

    /**
     * Student Submit.
     *
     * @param stdClass $submission
     * @param int $teacherid
     * @return response
     * @throws dml_exception
     */
    public function student_submit(stdClass $submission, int $teacherid): response {
        global $USER;
        $teacheruser = core_user::get_user($teacherid);
        $student = $USER->username;
        $teacher = $teacheruser->username;
        $open = $this->document->get_open($student);
        $sub = $this->document->get_submission($student);
        $res_copy = $this->copy_file($open, $sub);
        if ($res_copy->success) {
            $res_share_teacher_edit = $this->set_permission($sub, $teacher, self::PERMISSION_ALL);
            if ($res_share_teacher_edit->success) {
                $res_share_student_read = $this->set_permission($sub, $student, self::PERMISSION_READ);
                if ($res_share_student_read->success) {
                    $res_listing = $this->listing($sub);
                    if ($res_listing->success) {
                        $tipnc = tipnc::get($submission->id);
                        if ($tipnc) {
                            $tipnc->ncid = $res_listing->data;
                            tipnc::update($tipnc);
                            return new response(true, $tipnc->ncid);
                        } else {
                            $tipnc = new stdClass();
                            $tipnc->assignment = $submission->assignment;
                            $tipnc->submission = $submission->id;
                            $tipnc->ncid = $res_listing->data;
                            tipnc::set($tipnc);
                            return new response(true, $tipnc->ncid);
                        }
                    } else {
                        tipnc_error::log(
                            'student_submit:listing',
                            $res_listing->error, $this->instance, $submission->id);
                        return $res_listing;
                    }
                } else {
                    tipnc_error::log(
                        'student_submit:set_permission',
                        $res_share_student_read->error, $this->instance, $submission->id);
                    return $res_share_student_read;
                }
            } else {
                tipnc_error::log(
                    'student_submit:set_permission',
                    $res_share_teacher_edit->error, $this->instance, $submission->id);
                return $res_share_teacher_edit;
            }
        } else {
            tipnc_error::log(
                'student_submit:copy_file',
                $res_copy->error, $this->instance, $submission->id);
            return $res_copy;
        }
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
        $params = [];
        try {
            $curl->get($url, $params, $this->get_options_curl('COPY'));
            $response = $curl->getResponse();

            if ($response['HTTP/1.1'] === '201 Created' || $response['HTTP/1.1'] === '204 No Content') {
                $response = new response(true, '');
            } else {
                $response = new response(false, null, new error('0101', $response['HTTP/1.1']));
            }
        } catch (\Exception $e) {
            $response = new response(false, null,
                new error('0100', $e->getMessage()));
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
        $url = $this->host . '/remote.php/dav/files/' . $this->user . '/' . $file . '?format=xml';
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
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0
            ));
            $response = curl_exec($curl);

            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);

            if ( $httpCode >= 400 || !empty($error) ){
                curl_close($curl);
                $response = new response(
                    false,
                    null,
                    new error('0203', 'Status Code: ' . $httpCode . ' - '. $error));
                return $response;
            } else {
                $xml = str_replace('d:', '', $response);
                $xml = str_replace('oc:', '', $xml);
                $xml = str_replace('nc:', '', $xml);
                $xml = simplexml_load_string($xml);

                curl_close($curl);

                if ($xml === false) {
                    $response = new response(
                        false, null, new error('0202', 'XML has errors'));
                    return $response;
                }
                if (isset($xml->response->propstat->prop->fileid)) {
                    $fileid = current($xml->response->propstat->prop->fileid);
                    $response = new response(true, $fileid);
                } else {
                    $response = new response(
                        false, null, new error('0201', 'The FileID could not be retrieved'));
                }
            }

        } catch (\Exception $e) {
            $response = new response(false, null,
                new error('0200', $e->getMessage()));
        }
        return $response;
    }

    /**
     * Set Permission.
     *
     * @param string $file
     * @param string $username
     * @param int $permission
     * @return response
     */
    protected function set_permission(string $file, string $username, int $permission): response {
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
                    $response = new response(false, null, new error('0302', 'Respuesta no esperada'));
                }
            } else {
                $response = new response(false, null, new error('0301', $response['HTTP/1.1']));
            }
        } catch (\Exception $e) {
            $response = new response(false, null,
                new error('0300', $e->getMessage()));
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
            $curl->post($url, json_encode($params), $this->get_options_curl('DELETE'));
            $response = $curl->getResponse();
            if ($response['HTTP/1.1'] === '200 OK') {
                $response = new response(true, '');
            } else {
                $response = new response(false, null, new error('0401', $response['HTTP/1.1']));
            }
        } catch (\Exception $e) {
            $response = new response(false, null,
                new error('0400', $e->getMessage()));
        }
        return $response;
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
