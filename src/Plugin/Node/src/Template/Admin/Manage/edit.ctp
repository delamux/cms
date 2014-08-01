<?php
/**
 * Licensed under The GPL-3.0 License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since	 2.0.0
 * @author	 Christopher Castro <chris@quickapps.es>
 * @link	 http://www.quickappscms.org
 * @license	 http://opensource.org/licenses/gpl-3.0.html GPL-3.0 License
 */
?>

<?php echo $this->Form->create($node, ['type' => 'file']); ?>
	<fieldset>
		<legend><?php echo __d('node', 'Basic Information'); ?></legend>
			<?php echo $this->Form->input('title'); ?>
			<em class="help-block">
				<?php echo __d('node', 'Slug'); ?>: <?php echo __d('node', $node->slug); ?>,
				<?php echo __d('node', 'URL'); ?>: <?php echo $this->Html->link("/{$node->node_type_slug}/{$node->slug}.html", $node->url, ['target' => '_blank']); ?>
			</em>

			<?php echo $this->Form->input('regenerate_slug', ['type' => 'checkbox', 'label' => __d('node', 'Regenerate Slug')]); ?>
			<em class="help-block"><?php echo __d('node', 'Check this to generate a new slug from title.'); ?></em>

			<?php echo $this->Form->input('description'); ?>
			<em class="help-block"><?php echo __d('node', 'A short description (200 chars. max.) about this content. Will be used as page meta-description when rendering this content node.'); ?></em>
	</fieldset>

	<fieldset>
		<legend><?php echo __d('node', 'Publishing'); ?></legend>
		<?php echo $this->Form->input('status', ['type' => 'checkbox', 'label' => __d('node', 'Published')]); ?>
		<?php echo $this->Form->input('promote', ['type' => 'checkbox', 'label' => __d('node', 'Promoted to front page')]); ?>
		<?php echo $this->Form->input('sticky', ['type' => 'checkbox', 'label' => __d('node', 'Sticky at top of lists')]); ?>
	</fieldset>

	<?php if (isset($node->_fields)): ?>
	<fieldset>
		<legend><?php echo __d('node', 'Content'); ?></legend>
		<?php foreach ($node->_fields as $field): ?>
			<?php echo $this->Form->input($field); ?>
		<?php endforeach; ?>
	</fieldset>
	<?php endif; ?>

	<fieldset>
		<legend><?php echo __d('node', 'Settings'); ?></legend>
			<?php echo $this->Form->input('comment_status', ['label' => __d('node', 'Comments'), 'options' => [1 => __d('node', 'Open'), 0 => __d('node', 'Closed'), 2 => __d('node', 'Read Only')]]); ?>
			<?php echo $this->Form->input('language', ['label' => __d('node', 'Language'), 'options' => $languages, 'empty' => __d('node', '-- ANY --')]); ?>
	</fieldset>

	<?php if (isset($node->node_revisions)): ?>
	<fieldset>
		<legend><?php echo __d('node', 'Revisions'); ?></legend>
		
		<table class="table table-hover">
			<thead>
				<tr>
					<th><?php echo __d('node', 'Title'); ?></th>
					<th><?php echo __d('node', 'Description'); ?></th>
					<th><?php echo __d('node', 'Language'); ?></th>
					<th><?php echo __d('node', 'Revision date'); ?></th>
					<th><?php echo __d('node', 'Actions'); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($node->node_revisions as $revision): ?>
				<tr>
					<td><?php echo $revision->data->title; ?></td>
					<td><?php echo !empty($revision->data->description) ? $revision->data->description : '---'; ?></td>
					<td><?php echo $revision->data->language ? $revision->data->language : __d('node', '--any--'); ?></td>
					<td><?php echo $revision->created->format(__d('node', 'Y-m-d H:i:s')); ?></td>
					<td>
						<?php echo $this->Html->link('', ['plugin' => 'Node', 'controller' => 'manage', 'action' => 'edit', $node->id, $revision->id], ['class' => 'btn btn-default glyphicon glyphicon-edit', 'title' => __d('node', 'Load revision')]); ?>
						<?php echo $this->Html->link('', ['plugin' => 'Node', 'controller' => 'manage', 'action' => 'delete_revision', $node->id, $revision->id], ['class' => 'btn btn-default glyphicon glyphicon-trash', 'title' => __d('node', 'Delete revision'), 'confirm' => __d('node', 'You are about to delete: "%s". Are you sure ?', $revision->data->title)]); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<tbody>
		</table>
	</fieldset>
	<?php endif; ?>

	<?php echo $this->Form->submit(__d('node', 'Save All')); ?>
<?php echo $this->Form->end(); ?>