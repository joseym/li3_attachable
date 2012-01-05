<?php
/**
 * li3_attachable: the most rad li3 file uploader.
 *
 * @copyright     Copyright 2012, Tobias Sandelius (http://sandelius.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_attachable\extensions\data\behavior;

use RuntimeException;
use lithium\util\Inflector;
use li3_attachable\extensions\Interpolation;

/**
 * The `Attachable` class allow us to manage file attachments as regular model fields.
 *
 * There are two ways to bind `Attachable` to your models. The first one is to use
 * the `li3_behaviors` plugin:
 *
 * {{{
 * class MyModel extends \li3_behaviors\extensions\Model {
 *
 *     protected $_actsAs = array(
 *         'Attachable' => array(
 *             'field_name' => array(...)
 *         )
 *     );
 * }
 * }}}
 *
 * The second approach is to call the static `__init` method in your models:
 *
 * {{{
 * use li3_attachable\extensions\data\behavior\Attachable;
 *
 * class MyModel extends \lithium\data\Model {
 *
 *     public static function __init() {
 *         parent::__init();
 *         Attachable::bind(__CLASS__, array(
 *             'field_name' => array(...)
 *         ));
 *     }
 * }
 * }}}
 *
 * @uses li3_attachable\extensions\Interpolation::run
 */
class Attachable {

    /**
     * Binds `Attachable` to a model. Se the class docs for information on how to connect
     * `Attachable` to your models.
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
            $delete = array();
            foreach ($attachments as $field => $info) {
                if ($self::hasField($field)) {
                    $value = $entity->{$field};
                    if (is_array($value) && !empty($value['name'])) {
                        $value['name'] = str_replace(array(' '), array('_'), strtolower($value['name']));
                        $static::_prepareValidation($model, $field, $value);
                        $delete[$field]   = $export['data'][$field];
                        $upload[$field]   = $value;
                        $entity->{$field} = $value['name'];
                    } elseif (is_array($value) && empty($value['name'])) {
                        $entity->{$field} = $export['data'][$field];
                    } elseif (empty($value) && !empty($export['data'][$field])) {
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
     * @see li3_attachable\extensions\Interpolation::run
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
        throw new RuntimeException("Unable to upload file `{$file}`.");
    }

    /**
     * Delete an existing attachment.
     *
     * This will also remove the directory, where the file was located, if it is empty
     * after we've deleted the attachment.
     *
     * @param object $entity
     * @param string $field
     * @param string $name
     * @param array $config
     * @return boolean
     * @see li3_attachable\extensions\Interpolation::run
     */
    public static function _deleteAttachment($entity, $field, $name, $config) {
        $file = Interpolation::run($config['path'], $entity, $field, array(
            'filename' => $name
        ));
        $path = dirname($file);
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
     * @param object $model
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