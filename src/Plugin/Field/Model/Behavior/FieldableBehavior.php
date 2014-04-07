<?php
/**
 * Licensed under The GPL-3.0 License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since	 1.0.0
 * @author	 Christopher Castro <chris@quickapps.es>
 * @link	 http://www.quickappscms.org
 * @license	 http://opensource.org/licenses/gpl-3.0.html GPL-3.0 License
 */
namespace Field\Model\Behavior;

use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Error\MethodNotAllowedException;
use Cake\ORM\Behavior;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\ORM\Query;
use QuickApps\Utility\HookTrait;

/**
 * Fieldable Behavior
 *
 * Allows additional fields to be attached to Tables.
 * Any Table (Nodes, Users, etc.) can use this behavior to make itself `field-able` and thus allow
 * fields to be attached to it.
 *
 * The Field API defines two primary data structures, FieldInstance and FieldValues:
 *
 * - FieldInstance: is a Field attached to a single Table. (Schema equivalent: column)
 * - FieldValue: is the stored data for a particular [FieldInstance, Entity] tuple of your Table. (Schema equivalent: cell value)
 *
 * **Basically, this behavior allows you to add _virtual columns_ to your table schema.**
 *
 *
 * ***
 *
 *
 * This behavior modifies each query of your table in order to
 * merge custom-fields records into each entity under the `_fields` property.
 *
 * ## Entity Example:
 *
 *     // $user = $this->Users->get(1);
 *     // User' properties may looks:
 *     [id] => 1,
 *     [password] => e10adc3949ba59abbe56e057f20f883e,
 *     ...
 *     [_fields] => [
 *         [0] => [
 *             [name] => user_age,
 *             [label] => User Age,
 *             [value] => 22,
 *             [extra] => null,
 *             [metadata] => [ ... ]
 *         ],
 *         [1] => [
 *             [name] => user_phone,
 *             [label] => User Phone,
 *             [value] => null,  // no data stored,
 *             [extra] => null, // no data stored
 *             [metadata] => [ ... ]
 *         ],
 *         ...
 *         [n] => [ ... ]
 *     )
 *
 * In the example above, User entity has a custom field named `user_age` and its current value is 22.
 * In the other hand, it also has a `user_phone` field but no information was given (Schema equivalent: NULL cell).
 *
 * As you might see, the `_field` key contains an array list of all fields attached to your entity.
 * Each field is an object (Field Entity), and it have a number of properties
 * such as `label`, `value`, etc. All properties are described below:
 *
 * - `name`: Machine-name of this field. ex. `article_body` (Schema equivalent: column name).
 * -  `label`: Human readable name of this field e.g.: `User Last name`.
 * -  `value`: Value for this [field, entity] tuple. (Schema equivalent: cell value)
 * -  `extra`: Extra data for Field Handler.
 * -  `metadata`: Metadata (an entity object).
 *     - `field_value_id`: ID of the value stored in `field_values` table.
 *     - `field_instance_id`: ID of field instance (`field_instances` table) attached to the table.
 *     - `entity_id`: ID of the Entity this field is attached to.
 *     - `table_alias`: Name of the table this field is attached to. e.g: `users`.
 *     - `description`: Something about this field: e.g.: `Please enter your last name`.
 *     - `required`: 0|1.
 *     - `settings`: Any extra information array handled by this particular field.
 *     - `handler`: class name of the Field Handler under `Field` namespace. e.g.: `Text` (Field\Text)
 *
 * Note that `metadata` is actually an entity object.
 *
 * ### Accessing Field Properties
 *
 * Once you have your Entity (e.g. User Entity), you would probably need to get its attached fields
 * and do fancy thing with them. Following with our User entity example:
 *
 *     // In your controller: get an user entity
 *     $user = $this->Users->get($id);
 *     echo $user->_fields[0]->label . ': ' . $user->_fields[0]->value;
 *     // out: "User Age: 22"
 *
 *     echo "This field is attached to '" . $user->_fields[0]->metadata->table_alias . "' table";
 *     // out: "This field is attached to 'users' table";
 *
 * ## Searching over custom fields
 *
 * This behavior allows you to perform WHERE conditions using
 * any of the fields attached to your table. Every attached field has a "machine-name" (a.k.a field slug),
 * you should use this "machine-name" prefixed with `:`, for example:
 *
 *     TableRegistry::get('Users')
 *         ->find()
 *         ->where(['Users.:first-name LIKE' => 'John%'])
 *         ->all();
 *
 * `Users` table has a custom field attached (first_name), and we are looking
 * for all the users whose `first_name` starts with `John`.
 *
 * ## Value vs Extra
 *
 * In the "Entity Example" above you might notice that each field attached to entities has two properties that looks
 * pretty similar, `value` and `extra`, as both are intended to store information. Here we explain the "why" of this.
 *
 * Field Handlers may store complex information or structures.
 * For example, `AlbumField` may store a list of photos for each entity. In those cases
 * you should use the `extra` property to store your array list of photos,
 * while `value` property should always store a Human-Readable representation of your field's value.
 *
 * In our `AlbumField` example, we could store an array list of file names and titles
 * for a given entity under the `extra` property. And we could save photo's titles as space-separated
 * values under `value` property:
 *
 *     // extra:
 *     [photos] => [
 *         ['title' => 'OMG!', 'file' => 'omg.jpg'],
 *         ['title' => 'Look at this, lol', 'file' => 'cats-fighting.gif'],
 *         ['title' => 'Fuuuu', 'file' => 'fuuuu-meme.png'],
 *     ]
 *
 *     // value
 *     OMG! Look at this lol Fuuuu
 *
 * As information stored in `value` property will not be used when rendering
 * nodes (we'll actually render photos using `extra` information) you can safely store
 * a list of words that would acts like some kind of `words index`.
 *
 * **Important: FieldableBehavior automatically serializes/unserializes the `extra` property for you**,
 * so you should always treat `extra` as an array of information.
 *
 * `Search over fields` feature described above uses `value`, so in this way
 * your entities can be found when using Field's machine-name on where-conditions.
 *
 * Using `extra` is not mandatory, your Field Handler could use an additional
 * table schema to store entities information and leave `extra` as NULL. In that
 * case, your Field Handler must take care of joining entities with that external table
 * of information.
 *
 * So basically, `value` is intended to store `plain text` information, while
 * `extra` is intended to store arrays of information.
 *
 * ***
 *
 * ## Using this behavior
 *
 * Just like any other behavior, in your Table constructor attach this behavior as usual:
 *
 *     $this->attachBehavior('Field.Fieldable');
 *
 * ## Enable/Disable Field Attachment
 *
 * If for some reason you don't need custom fields to be fetched you should use the
 * unbindFieldable(). Or bindFieldable() enable it again.
 *
 *     // there wont be a "_field" key on your User entity
 *     $this->User->unbindFieldable();
 *     $this->Users->get($id);
 *
 * ## Field-Handler & Hooks
 *
 * Field Handler are "Listeners" classes which must take care of storing, organizing and retrieving information for each entity
 * in your table. This is archived using Hook callbacks.
 *
 * Similar to Hooks and Hooktags, Field Handlers must define a series of hook event. This hook events
 * has been organized in two groups or "event subspaces":
 *
 * - `Field.<FieldHandler>.Entity`: For handling Entity's related events such
 * as `entity save`, `entity delete`, etc.
 * - `Field.<FieldHandler>.Instance`: Related to Field Instances events, such as
 * "instance being detached from table", "new instance attached to table", etc.
 *
 * Below, a list of available hook events:
 *
 * - `Field.<FieldHandler>.Entity.display`: When an entity is being rendered
 * - `Field.<FieldHandler>.Entity.edit`: When an entity is being rendered in `edit` mode. (backend usually)
 * - `Field.<FieldHandler>.Entity.beforeFind`: Before an entity is retrieved from DB
 * - `Field.<FieldHandler>.Entity.beforeSave`: Before entity is saved
 * - `Field.<FieldHandler>.Entity.beforeValidate`: Before entity is validated as part of save operation
 * - `Field.<FieldHandler>.Entity.afterValidate`: After entity is validated as part of save operation
 * - `Field.<FieldHandler>.Entity.beforeDelete`: Before entity is deleted
 * - `Field.<FieldHandler>.Entity.afterDelete`: After entity was deleted
 *
 * - `Field.<FieldHandler>.Instance.info`: When QuickAppsCMS asks for information about each registered Field
 * - `Field.<FieldHandler>.Instance.settings`: Additional settings for this field. Should define the way the values will be stored in the database.
 * - `Field.<FieldHandler>.Instance.formatter`: Additional formatter options.
 * - `Field.<FieldHandler>.Instance.beforeAttach`: Before field is attached to Tables
 * - `Field.<FieldHandler>.Instance.afterAttach`: After field is attached to Tables
 * - `Field.<FieldHandler>.Instance.beforeDetach`: Before field is detached from Tables
 * - `Field.<FieldHandler>.Instance.afterDetach`: After field is detached from Tables
 */
class FieldableBehavior extends Behavior {

	use HookTrait;

/**
 * Table which this behavior is attached to.
 *
 * @var \Cake\ORM\Table
 */
	protected $_table;

/**
 * Used for reduce BD queries and allow inter-method communication.
 * Example, it allows to pass some information from beforeDelete() to afterDelete().
 *
 * @var array
 */
	protected $_cache = [];

/**
 * Default configuration.
 *
 * These are merged with user-provided configuration when the behavior is used.
 * Available options are:
 *
 * - table_alias: Name of the table being managed. Default null (auto-detect).
 * - polymorphic_table_alias: An entity's property name to use as `table_alias` whenever possible. Default null (use `table_alias` always).
 * - find_iterator:	Callable function to iterate over find result-set.
 * - fields_order: A list of field machine-names in which you want the `_fields` key
 * to be ordered. ex. `[article-intro, article-body]`. Ordered by `field_instances.ordering` key default
 * - enabled: True enables this behavior or false for disable. Default to true.
 *
 * When using `polymorphic_table_alias` feature, `table_alias` becomes:
 *
 *     <real_table_name>_<polymorphic_table_alias>
 *
 * For example, if you set `polymorphic_table_alias` to "type", and your table name is `users`,
 * `table_alias` will be
 *
 *     users_<type>
 *
 * Where `<type>` value will vary depending on each Entity's `type` column value from `Users` table.
 * Using this feature allow each Entity in your table to behave like a Table by it self.
 *
 * @var array
 */
	protected $_defaultConfig = [
		'table_alias' => null,
		'polymorphic_table_alias' => null,
		'find_iterator' => null,
		'fields_order' => [],
		'enabled' => true,
	];

/**
 * Constructor.
 *
 * @param \Cake\ORM\Table $table The table this behavior is attached to
 * @param array $config Configuration array for this behavior
 */
	public function __construct(Table $table, array $config = []) {
		$this->_table = $table;
		$config['table_alias'] = empty($config['table_alias']) ? strtolower($table->alias()) : $config['table_alias'];
		parent::__construct($table, $config);

		if (!is_callable($this->_config['find_iterator'])) {
			$this->_config['find_iterator'] = function ($entity, $key, $mapReduce) {
				$this->fieldableMapper($entity, $key, $mapReduce);
			};
		}
	}

/**
 * Returns a list of hooks this class is implementing. When the class is registered
 * in an event manager, each individual method will be associated with the respective event.
 *
 * @return void
 */
	public function implementedEvents() {
		$events = [
			'Model.beforeFind' => ['callable' => 'beforeFind', 'priority' => 15],
			'Model.beforeSave' => ['callable' => 'beforeSave', 'priority' => 15],
			'Model.afterSave' => ['callable' => 'afterSave', 'priority' => 15],
			'Model.beforeDelete' => ['callable' => 'beforeDelete', 'priority' => 15],
			'Model.afterDelete' => ['callable' => 'afterDelete', 'priority' => 15],
			'Model.beforeValidate' => ['callable' => 'beforeValidate', 'priority' => 15],
			'Model.afterValidate' => ['callable' => 'afterValidate', 'priority' => 15],
		];

		return $events;
	}

/**
 * Modifies the query object in order to merge custom fields records
 * into each entity under the `_fields` property.
 *
 * It also looks for custom fields in where-conditions.
 *
 * @param \Cake\Event\Event $event The beforeFind event that was fired
 * @param \Cake\ORM\Query $query The original query to modify
 * @param array $options
 * @param boolean $primary
 * @return void
 */
	public function beforeFind(Event $event, $query, $options, $primary) {
		$config = $this->config();

		if (!$config['enabled']) {
			return true;
		}

		$query = $this->_parseQuery($query, $options);
		$query->mapReduce($config['find_iterator']);
	}

/**
 * Before an entity is saved.
 *
 * @param \Cake\Event\Event $event
 * @param \Cake\ORM\Entity $entity
 * @param array $options
 * @return void
 */
	public function beforeSave(Event $event, $entity, $options) {
		$config = $this->config();

		if (!$config['enabled']) {
			return;
		}

		$instances = $this->_getTableFieldInstances($entity);
		$EventManager = $this->_getEventManager();

		foreach ($instances as $instance) {
			$field = $this->_getMockField($entity, $instance);
			$fieldEvent = $this->invoke("Field.{$instance->handler}.Entity.beforeSave", $event->subject, $field, $options);
		}
	}

/**
 * Callback fired before saving each Table's Entity.
 * We dispatch each custom field's $_POST information to its corresponding Field Handler, so
 * they can operate over values before Entity is saved.
 *
 * Fields Handler's `Field.<FieldHandler>.Entity.afterSave` hook is fired, so you should have a listener like so:
 *
 *     class TextField implements EventListener {
 *         public function implementedEvents() {
 *             return [
 *                 'Field\TextField.Entity.afterSave' => 'entityBeforeSave',
 *             ];
 *         }
 *
 *         public function entityBeforeSave(Event $event, &$field, &$options) {
 *              // alter $field, and do nifty things with $options[post]
 *              // return false; will halt the save operation
  *         }
 *     }
 *
 * Note that returning boolean FALSE will halt the Table Entity's save operation.
 *
 * Also, you will see `$options` array contains the post information user just sent when
 * pressing form submit button.
 *
 *     $options[post]: $_POST information for this [entity, field_instance] tuple.
 *
 * Field Handlers should **alter** `$field->value` and `$field->extra`
 * according to its needs **using $options[post]**. Remember that returning FALSE will halt the
 * whole save operation.
 *
 * ## Rendering Field Form Elements
 *
 * Your Field Handler should somehow render some form elements (inputs, selects, textareas, etc)
 * when rendering Table Entities in `edit mode`. For this we have the ``Field.<FieldHandler>.Entity.edit` hook,
 * which should return a HTML containing all the form elements for [entity, field_instance] tuple.
 *
 * For example, lets suppose we have a `TextField` attached to `Users` Table for storing their `favorite_food`,
 * and now we are editing some specific `User` Entity (i.e.: User.id = 4),
 * so in the form editing page we should see some inputs for change some values
 * like `username` or `password`, and also we should see a `favorite_food` input
 * where Users shall type in their favorite food. Well, your TextField Handler should return something like this:
 *
 *     // note the `:` prefix
 *     <input name=":favorite_food" value="<current_value_from_entity>" />
 *
 * To accomplish this, your Field Handler should catch the ``Field.<FieldHandler>.Entity.edit` hook properly, example:
 *
 *     public function entityEdit(Event $event, $field) {
 *         return '<input name=":' . $field->name . '" value="' . $field->value . '" />";
 *     }
 *
 * As usual, the second argument `$field` contains all the information you will need to properly
 * render your form inputs.
 *
 * You must tell to QuickAppsCMS that the fields you are sending in your POST action are actually
 * virtual fields. To do this, all your input's `name` attributes **must be prefixed** with `:` followed by its machine (a.k.a. `slug`) name:
 *
 *     <input name=":<machine-name>" ... />
 *
 * You may also create complex data structures like so:
 *
 *     <input name=":album.name" value="<current_value>" />
 *     <input name=":album.photo.0" value="<current_value>" />
 *     <input name=":album.photo.1" value="<current_value>" />
 *     <input name=":album.photo.2" value="<current_value>" />
 *
 * The above may produce a $_POST array like below:
 *
 *         :album => array(
 *             name => Album Name,
 *             photo => array(
 *                 0 => url_image1.jpg,
 *                 1 => url_image2.jpg,
 *                 2 => url_image3.jpg
 *             )
 *         ),
 *         ...
 *         :other_field => ...,
 *     )
 *
 * **Remember**, you should always rely on View::elements() for rendering HTML code:
 *
 *     public function editTextField(Event $event, $field) {
 *         $view = $event->subject;
 *         return $View->element('text_field_edit', ['field' => $field]);
 *     }
 *
 * ## Creating an Edit Form
 *
 * In previous example we had an User edit form. When rendering User's form-inputs
 * usually you would do something like so:
 *
 *     // edit.ctp
 *     <?php echo $this->Form->input('id', ['type' => 'hidden']); ?>
 *     <?php echo $this->Form->input('username'); ?>
 *     <?php echo $this->Form->input('password'); ?>
 *
 * When rendering virtual fields you can pass the whole Field Object to `FormHelper::input()` method.
 * So instead of passing the input name as first argument (as above) you can do as follow:
 *
 *     // Remember, custom fields are under the `_fields` property of your entity
 *     <?php echo $this->Form->input($user->_fields[0]); ?>
 *     <?php echo $this->Form->input($user->_fields[1]); ?>
 *
 * That will render the first and second virtual field attached to your entity. But usually you'll end
 * creating some loop structure and render all of them at once:
 *
 *     <?php foreach ($user->_fields as $field): ?>
 *         <?php echo $this->Form->input($field); ?>
 *     <?php endforeach; ?>
 *
 * As you may see, `Form::input()` **automagically fires** the `Field.<FieldHandler>.Entity.edit` hook
 * asking to the corresponding Field Handler for its HTML form elements.
 * Passing the Field object to `Form::input()` is not mandatory, you can manually generate your
 * input elements:
 *
 *     <input name=":<?= $field->name; ?>" value="<?= $field->value; ?>" />
 *
 * The `$user` variable used in these examples assumes you used `Controller::set()` method in your
 * controller.
 *
 * A more complete example:
 *
 *     // UsersController.php
 *     public function edit($id) {
 *         $this->set('user', $this->Users->get($id));
 *     }
 *
 *     // edit.ctp
 *     <?php echo $this->Form->create($user); ?>
 *         <?php echo $this->Form->hidden('id'); ?>
 *         <?php echo $this->Form->input('username'); ?>
 *         <?php echo $this->Form->input('password'); ?>
 *         <!-- Custom Fields -->
 *         <?php foreach ($user->_fields as $field): ?>
 *             <?php echo $this->Form->input($field); ?>
 *         <?php endforeach; ?>
 *         <!-- /Custom Fields -->
 *         <?php echo $this->Form->submit('Save User'); ?>
 *     <?php echo $this->Form->end(); ?>
 *
 * @param \Cake\Event\Event $event
 * @param \Cake\ORM\Entity $entity
 * @param array $options
 * @throws \Cake\Error\FatalErrorException When using this behavior in non-atomic mode
 * @return boolean True if save operation should continue
 */
	public function afterSave(Event $event, $entity, $options) {
		$config = $this->config();

		if (!$config['enabled']) {
			return true;
		}

		if (!$options['atomic']) {
			throw new FatalErrorException(__('Entities in fieldable tables can only be saved using transaction. Set [atomic = true]'));
		}

		$pk = $this->_table->primaryKey();
		$table_alias = $this->_guessTableAlias($entity);
		$instances = $this->_getTableFieldInstances($entity);
		$EventManager = $this->_getEventManager();
		$FieldValues = $this->_getTable('Field.FieldValues');

		foreach ($instances as $instance) {
			$postValuesForField = $entity->has(":{$instance->slug}") ? $entity->get(":{$instance->slug}") : false;

			if ($postValuesForField) {
				$field = $this->_getMockField($entity, $instance);
				$options['post'] = $postValuesForField;
				$fieldEvent = $this->invoke("Field.{$instance->handler}.Entity.afterSave", $event->subject, $field, $options);

				if ($fieldEvent->isStopped() || $fieldEvent->result === false) {
					$entity = $this->attachEntityFields($entity);
					$event->stopPropagation();
					return false; // stop Table's afterSave event
				}

				$field->extra = (array)$field->extra;
				$valueEntity = new \Field\Model\Entity\FieldValue([
					'id' => $field->metadata['field_value_id'],
					'field_instance_id' => $field->metadata['field_instance_id'],
					'field_instance_slug' => $field->name,
					'entity_id' => $entity->{$pk},
					'table_alias' => $table_alias,
					'value' => $field->value,
					'extra' => @serialize($field->extra)
				]);

				if (!$FieldValues->save($valueEntity)) {
					$entity = $this->attachEntityFields($entity);
					$event->stopPropagation();
					return false;
				}

				$entity->unsetProperty(":{$instance->slug}");
			}
		}

		$entity = $this->attachEntityFields($entity);
		return true;
	}

/**
 * Before entity validation process.
 *
 * @param \Cake\Event\Event $event
 * @param \Cake\ORM\Entity $entity
 * @param array $options
 * @param Validator $validator
 * @return boolean True on success
 */
	public function beforeValidate(Event $event, $entity, $options, $validator) {
		$config = $this->config();

		if (!$config['enabled']) {
			return true;
		}

		$EventManager = $this->_getEventManager();
		$instances = $this->_getTableFieldInstances($entity);

		foreach ($instances as $instance) {
			$field = $this->_getMockField($entity, $instance);
			$fieldEvent = $this->invoke("{$field->metadata['handler']}.Entity.beforeValidate", $event->subject, $field, $options, $validator);

			if ($fieldEvent->result === false || $fieldEvent->isStopped()) {
				$entity = $this->attachEntityFields($entity);
				$event->stopPropagation();
				return false;
			}
		}
	}

/**
 * After entity validation process.
 *
 * @param \Cake\Event\Event $event
 * @param \Cake\ORM\Entity $entity
 * @param array $options
 * @param Validator $validator
 * @return boolean True on success
 */
	public function afterValidate(Event $event, $entity, $options, $validator) {
		$config = $this->config();

		if (!$config['enabled']) {
			return true;
		}

		$EventManager = $this->_getEventManager();
		$instances = $this->_getTableFieldInstances($entity);

		foreach ($instances as $instance) {
			$field = $this->_getMockField($entity, $instance);
			$fieldEvent = $this->invoke("{$field->metadata['handler']}.Entity.afterValidate", $event->subject, $field, $options, $validator);

			if ($fieldEvent->result === false || $fieldEvent->isStopped()) {
				$entity = $this->attachEntityFields($entity);
				$event->stopPropagation();
				return false;
			}
		}
	}

/**
 * Deletes an entity from a fieldable table.
 *
 * This method automatically removes all field values
 * from `field_values` database table for each entity.
 *
 * @param \Cake\Event\Event $event
 * @param \Cake\ORM\Entity $entity
 * @param array $options
 * @return boolean
 * @throws \Cake\Error\FatalErrorException When using this behavior in non-atomic mode
 */
	public function beforeDelete(Event $event, $entity, $options) {
		$config = $this->config();
		$table_alias = $this->_guessTableAlias($entity);

		if (!$config['enabled']) {
			return true;
		}

		if (!$options['atomic']) {
			throw new FatalErrorException(__('Entities in fieldable tables can only be deleted using transaction. Set [atomic = true]'));
		}

		$instances = $this->_getTableFieldInstances($entity);
		$EventManager = $this->_getEventManager();
		$FieldValues = $this->_getTable('Field.FieldValues');

		foreach ($instances as $instance) {
			// invoke field beforeDelete so they can do its stuff
			// i.e.: Delete entity information from another table.
			$field = $this->_getMockField($entity, $instance);
			$fieldEvent = $this->invoke("Field.{$instance->handler}.Entity.beforeDelete", $event->subject, $field, $options);

			if ($fieldEvent->result === false || $fieldEvent->isStopped()) {
				$event->stopPropagation();
				return false;
			}

			$valueToDelete = $FieldValues->find()
				->where([
					'entity_id' => $entity->get($this->_table->primaryKey()),
					'table_alias' => $table_alias
				])->first();

			if ($valueToDelete) {
				$success = $FieldValues->delete($valueToDelete);

				if (!$success) {
					return false;
				}
			}

			// holds in cache field mocks, so we can catch them on afterDelete
			$this->_cache['fields.beforeDelete'][] = $field;
		}

		return true;
	}

/**
 * After an entity was removed from database.
 *
 * @param \Cake\Event\Event $event
 * @param \Cake\ORM\Entity $entity
 * @param array $options
 * @throws \Cake\Error\FatalErrorException When using this behavior in non-atomic mode
 * @return void
 */
	public function afterDelete(Event $event, $entity, $options) {
		$config = $this->config();

		if (!$config['enabled']) {
			return;
		}

		if (!$options['atomic']) {
			throw new FatalErrorException(__('Entities in fieldable tables can only be deleted using transactions. Set [atomic = true]'));
		}

		$EventManager = $this->_getEventManager();

		if (isset($this->_cache['fields.beforeDelete']) && is_array($this->_cache['fields.beforeDelete'])) {
			foreach ($this->_cache['fields.beforeDelete'] as $field) {
				$fieldEvent = $this->invoke("Field.{$field->handler}.Entity.afterDelete", $event->subject, $field, $options);

				if ($fieldEvent->result === false || $fieldEvent->isStopped()) {
					$event->stopPropagation();
					$this->_cache['fields.beforeDelete'] = [];
					return false;
				}
			}

			$this->_cache['fields.beforeDelete'] = [];
		}

		return true;
	}

/**
 * Iterates over each entity from result-set and fetches
 * custom fields under the `_fields` property.
 *
 * @param \Cake\ORM\Entity $entity The entity to modify
 * @param integer $key Entity key index from result collection.
 * @param MapReduce $mapReduce Instance of the MapReduce routine it is running.
 * @return void
 */
	public function fieldableMapper($entity, $key, $mapReduce) {
		$entity = $this->attachEntityFields($entity);
		$mapReduce->emit($entity, $key);
	}

/**
 * Changes behavior's configuration parameters.
 *
 * Useful when using callable mapper, so you
 * may change configuration parameters on every mapper's iteration
 * depending on your needs.
 *
 * @param array $config Configuration assoc-array
 * @return void
 */
	public function configureFieldable($config) {
		$this->_config = array_merge($this->_config, $config);
		$this->_config['table_alias'] = strtolower($this->_config['table_alias']);
	}

/**
 * Enables this behavior.
 *
 * @return void
 */
	public function bindFieldable() {
		$this->_config['enabled'] = true;
	}

/**
 * Disables this behavior.
 *
 * @return void
 */
	public function unbindFieldable() {
		$this->_config['enabled'] = false;
	}

/**
 * The method which actually fetches custom fields.
 *
 * Fetches all Entity's fields under the `_fields` property.
 * It also sorts custom-fields if `fields_order` configuration parameter
 * was set.
 *
 * @param \Cake\ORM\Entity $entity The entity where to fetch fields
 * @return \Cake\ORM\Entity Modified $entity
 */
	public function attachEntityFields($entity) {
		$config = $this->config();

		if (!($entity instanceof \Cake\ORM\Entity)) {
			return $entity;
		}

		$entity->accessible('*', true);
		$_fields = [];

		foreach ($this->_getTableFieldInstances($entity) as $instance) {
			$mock = $this->_getMockField($entity, $instance);

			// restore from $_POST
			if ($entity->has(":{$instance->slug}")) {
				$value = $entity->get(":{$instance->slug}");

				if (is_string($value)) {
					$mock->set('value', $value);
				} else {
					$mock->set('extra', (array)$value);
				}
			}

			$_fields[] = $mock;
		}

		if (!empty($config['fields_order']) && is_array($config['fields_order'])) {
			$_aux = [];

			foreach ($config['fields_order'] as $slug) {
				foreach ($_fields as $k => $v) {
					if ($v->name == $slug) {
						$_aux[] = $v;
						unset($_fields[$k]);
					}
				}
			}

			if (!empty($_fields)) {
				$_aux = array_merge($_aux, $_fields);
			}

			$_fields = $_aux;
		}

		$entity->set('_fields', $_fields);
		return $entity;
	}

/**
 * Look for `:<machine-name>` patterns in query conditions.
 *
 * Allows to search entities using custom fields as conditions.
 *
 * @param \Cake\ORM\Query $query
 * @param array $options
 * @return \Cake\ORM\Query The modified query object
 */
	public function _parseQuery($query, $options) {
		$config = $this->config();
		list($table_alias, $find_iterator, $enabled) = [$config['table_alias'], $config['find_iterator'], $config['enabled']];
		$whereClause = $query->clause('where');

		if ($whereClause) {
			$table_alias = !empty($options['table_alias']) ? $options['table_alias'] : $table_alias;
			$whereClause->traverse(function ($expression) use($table_alias) {
				if (!($expression instanceof \Cake\Database\Expression\Comparison)) {
					return;
				}

				$field = $expression->getField();
				$value = $expression->getValue();
				$conjunction = $expression->type();
				list($entity_name, $field_name) = pluginSplit($field);

				if (!$field_name) {
					$field_name = $entity_name;
				}

				$field_name = preg_replace('/\s{2,}/', ' ', $field_name);
				list($field_name, ) = explode(' ', trim($field_name));

				if (strpos($field_name, ':') !== 0) {
					return;
				}

				$field_name = str_replace(':', '', $field_name);
				$subQuery = $this->_getTable('Field.FieldValues')->find()
					->select('entity_id')
					->where(
						[
							"FieldValues.field_instance_slug" => $field_name,
							"FieldValues.value {$conjunction}" => $value
						]
					);

				if (is_array($table_alias)) {
					$subQuery->where(['FieldValues.table_alias IN' => $table_alias]);
				} elseif (strpos($table_alias, '%') !== false) {
					$subQuery->where(['FieldValues.table_alias LIKE' => $table_alias]);
				} else {
					$subQuery->where(['FieldValues.table_alias' => $table_alias]);
				}

				$expression->field($this->_table->alias() . '.' . $this->_table->primaryKey());
				$expression->value($subQuery);
				$expression->type('IN');

			});
		}

		return $query;
	}

/**
 * Creates a new "Field" for each entity.
 *
 * This mock Field represents a new property (table column) in
 * your entity.
 *
 * @param \Cake\ORM\Entity $entity The entity where to attach fields
 * @param \Field\Model\Entity\FieldInstance $instance The instance
 * where to get the information when creating the mock field.
 * @return \Field\Model\Entity\Field
 */
	protected function _getMockField($entity, $instance) {
		$pk = $this->_table->primaryKey();
		$FieldValues = $this->_getTable('Field.FieldValues');
		$storedValue = $FieldValues->find()
			->select(['id', 'value', 'extra'])
			->where(
				[
					'FieldValues.field_instance_id' => $instance->id,
					'FieldValues.table_alias' => $this->_guessTableAlias($entity),
					'FieldValues.entity_id' => $entity->get($this->_table->primaryKey())
				]
			)
			->first();

		$mockField = new \Field\Model\Entity\Field([
				'name' => $instance->slug,
				'label' => $instance->label,
				'value' => null,
				'extra' => null,
				'metadata' => new \Cake\ORM\Entity([
					'field_value_id' => null,
					'field_instance_id' => $instance->id,
					'entity_id' => $entity->{$pk},
					'table_alias' => $this->_guessTableAlias($entity),
					'description' => $instance->description,
					'required' => $instance->required,
					'settings' => $instance->settings,
					'handler' => $instance->handler,
				])
		]);

		if ($storedValue) {
			$mockField->metadata->accessible('*', true);
			$mockField->set('value', $storedValue->value);
			$mockField->set('extra', @unserialize((string)$storedValue->extra));
			$mockField->metadata->set('field_value_id', $storedValue->id);
			$mockField->metadata->accessible('*', false);
		}

		$mockField->isNew($entity->isNew());
		$mockField->accessible('*', false);
		$mockField->accessible('value', true);
		$mockField->accessible('extra', true);

		return $mockField;
	}

/**
 * Gets table alias this behavior is attached to.
 *
 * This method requires an entity, so we can properly take care of
 * the `polymorphic_table_alias` option.
 * If this option is not used, then `Table::alias()` is returned.
 *
 * @param \Cake\ORM\Entity $entity From where try to guess `polymorphic_table_alias`
 * @return string Table alias
 */
	protected function _guessTableAlias($entity) {
		$config = $this->config();
		$table_alias = $this->_config['table_alias'];

		if ($config['polymorphic_table_alias']) {
			$table_alias .= '_' . $entity->{$config['polymorphic_table_alias']};
		}

		return $table_alias;
	}

/**
 * Wrapper for TableRegistry::get().
 *
 * @param string $table
 * @return \Cake\ORM\Table
 */
	protected function _getTable($table) {
		return TableRegistry::get($table);
	}

/**
 * Wrapper for EventManager::instance().
 *
 * @return \Cake\Event\EventManager
 */
	protected function _getEventManager() {
		return EventManager::instance();
	}

/**
 * Used to reduce database queries.
 *
 * @param \Cake\ORM\Entity $entity
 * @return \Cake\ORM\Query Field instances attached to current table as a query result
 */
	protected function _getTableFieldInstances($entity) {
		$table_alias = $this->_guessTableAlias($entity);

		if (isset($this->_cache['TableFieldInstances'][$table_alias])) {
			return $this->_cache['TableFieldInstances'][$table_alias];
		} else {
			$FieldInstances = $this->_getTable('Field.FieldInstances');
			$this->_cache['TableFieldInstances'][$table_alias] =
				$FieldInstances->find()
				->where(['FieldInstances.table_alias' => $table_alias])
				->order(['ordering' => 'ASC'])
				->all();

			return $this->_cache['TableFieldInstances'][$table_alias];
		}
	}

}