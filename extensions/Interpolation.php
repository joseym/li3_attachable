<?php
/**
 * li3_attachable: the most rad li3 file uploader
 *
 * @copyright     Copyright 2012, Tobias Sandelius (http://sandelius.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_attachable\extensions;

use Closure;
use lithium\util\String;
use lithium\core\Libraries;

/**
 * The `Interpolation` class allow us to create dynamic paths and urls for our model
 * attachments. It's really easy to create your own custom interpolations.
 *
 * Here we'll add the, slugified, called class name as a dynamic option:
 *
 * {{{
 * use li3_attachable\extensions\Interpolation;
 * use lithium\util\Inflector;
 *
 * Interpolation::add('class', function($entity, $field) {
 *    return strtolower(Inflector::slug(get_class($entity)));
 * });
 * }}}
 *
 * After the above we can use `{:class}` in our attachment configurations.
 *
 * @see lithium\util\String::insert
 * @see lithium\core\Libraries::get
 */
class Interpolation {

    /**
     * Custom created interpolations.
     *
     * @var array
     * @see li3_phaperclip\data\Interpolation::add
     */
    protected static $_interpolation = array();

    /**
     * Add a new interpolation to the collection.
     *
     * {{{
     * use li3_attachable\extensions\Interpolation;
     * use lithium\util\Inflector;
     *
     * Interpolation::add('class', function($entity, $field) {
     *    return strtolower(Inflector::slug(get_class($entity)));
     * });
     * }}}
     *
     * Default interpolations we can use is:
     *
     * * `{:root}` ~ Path to the default library.
     * * `{:id}` ~ Entity id.
     * * `{:model}` ~ Lowercased name of the model, without namespaces.
     * * `{:filename}` ~ Name of the file with extension.
     *
     * @param string $name Name of the interpolation.
     * @param object $closure `Closure` object.
     * @return void
     */
    public static function add($name, Closure $closure) {
        static::$_interpolation[$name] = $closure;
    }

    /**
     * Run a string threw the interpolations.
     *
     * @param string $string
     * @param object $entity
     * @param string $field
     * @param array $data
     * @return string
     * @see lithium\util\String::insert
     * @see lithium\core\Libraries::get
     */
    public static function run($string, $entity, $field, array $data = array()) {
        $parts = explode('\\', $entity->model());
        $data += array(
            'id'       => $entity->id,
            'root'     => Libraries::get(true, 'path'),
            'model'    => strtolower(array_pop($parts)),
            'filename' => $entity->{$field}
        );
        foreach (static::$_interpolation as $name => $closure) {
            $data[$name] = $closure($entity, $field);
        }
        return String::insert($string, $data);
    }

    /**
     * Clear all custom created interpolations.
     *
     * @return void
     */
    public static function clear() {
        static::$_interpolation = array();
    }
}