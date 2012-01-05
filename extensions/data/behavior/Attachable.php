<?php
/**
 * li3_attachable: the most rad li3 file uploader
 *
 * @copyright     Copyright 2012, Tobias Sandelius (http://sandelius.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_attachable\extensions\data\behavior;

use RuntimeException;
use li3_attachable\extensions\Interpolation;

class Attachable {

    /**
     * Binds the class to a model.
     *
     * @param string $class
     * @param array $config
     * @return void
     */
    public static function bind($class, array $config = array()) {
        $attachments = array();
        foreach ($config as $field => $info) {
            $attachments[$field] = $info += array(
                'path' => '{:root}/webroot/files/{:id}/{:filename}',
                'url' => '/files/{:id}/{:filename}',
                'default' => '/img/missing.png'
            );
        }

        $static = __CLASS__;
        $class::applyFilter('save', function($self, $params, $chain) use ($attachments, $static) {
            $model = $self::invokeMethod('_object');
            $entity  = $params['entity'];
            $options = $params['options'];

            if ($params['data']) {
                $entity->set($params['data']);
                $params['data'] = null;
            }

            $export = $entity->export();
            $upload = array();
            foreach ($attachments as $field => $info) {
                if ($self::hasField($field)) {
                    $value = $entity->{$field};
                    if (is_array($value)) {
                        $static::_prepareValidation($model, $field, $value);
                        $delete[$field]   = $export['data'][$field];
                        $upload[$field]   = $value;
                        $entity->{$field} = $value['name'];
                    }
                    if (empty($export['update'][$field]) && !empty($export['data'][$field])) {
                        $delete[$field] = $export['data'][$field];
                    }
                }
            }

            // Save the object
            $result = $chain->next($self, $params, $chain);

            // Save succeeded, upload and delete files
            if ($result) {
                foreach ($delete as $field => $name) {
                    $static::_deleteAttachment($entity, $field, $name, $attachments[$field]);
                }
                foreach ($upload as $field => $info) {
                    $static::_uploadAttachment($entity, $field, $info, $attachments[$field]);
                }
            }

            return $result;
        });
    }

    /**
     * Upload a new attachment.
     *
     * @param object $entity
     * @param string $field
     * @param array $info
     * @param array $config
     * @return boolean
     * @throws RuntimeException
     */
    public static function _uploadAttachment($entity, $field, $info, $config) {
        $file = Interpolation::run($config['path'], $entity, $field);
        $path = dirname($file);
        if (!is_dir($path)) {
            mkdir($path, 02777, true);
            chmod($path, 02777);
        }
        if (@move_uploaded_file($info['tmp_name'], $file)) {
            return true;
        }
        rmdir($path);
        throw new RuntimeException("Unable to upload file to `{$file}`.");
    }

    /**
     * Delete an existing attachment.
     *
     * @param object $entity
     * @param string $field
     * @param string $name
     * @param array $config
     * @return boolean
     */
    public static function _deleteAttachment($entity, $field, $name, $config) {
        $file = Interpolation::run($config['path'], $entity, $field, array(
            'filename' => $name
        ));
        if (is_file($file) && unlink($file)) {
            $files = @scandir($path);
            if ($files && count($files) === 2) {
                rmdir($path);
            }
            return true;
        }
    }

    /**
     * Prepare the validation rules by adding the attachment information.
     *
     * @param object $entity
     * @param string $field
     * @param array $info
     * @return void
     */
    public static function _prepareValidation($model, $field, $info) {
        if (isset($model->validates[$field])) {
            foreach ($model->validates[$field] as $no => $rule) {
                if (in_array('attachmentSize', $rule)) {
                    $model->validates[$field][$no]['attachment'] = $info;
                } elseif (in_array('attachmentType', $rule)) {
                    $model->validates[$field][$no]['attachment'] = $info;
                }
            }
        }
    }
}