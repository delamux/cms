<?php
/**
 * Licensed under The GPL-3.0 License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @since    2.0.0
 * @author   Christopher Castro <chris@quickapps.es>
 * @link     http://www.quickappscms.org
 * @license  http://opensource.org/licenses/gpl-3.0.html GPL-3.0 License
 */
?>

<div class="row">
    <div class="col-md-12">
        <?php if (!empty($languages)): ?>
            <?= $this->Form->create($content); ?>
                <fieldset>
                    <legend><?= __d('content', 'Translating Content'); ?></legend>

                    <?= $this->Form->input('title', ['label' => $content->content_type->title_label . ' *']); ?>
                    <em class="help-block"><?= __d('content', 'New title for the translated version.'); ?></em>

                    <?= $this->Form->input('language', ['type' => 'select', 'label' => __d('content', 'Translate to'), 'options' => $languages]); ?>
                    <em class="help-block"><?= __d('content', 'Select the language to which you desire translate this content.'); ?></em>

                    <?= $this->Form->submit( __d('content', 'Continue')); ?>

                    <?php if ($translations->count()): ?>
                        <h3><?= __d('content', 'Available Translations'); ?></h3>
                        <ul>
                            <?php foreach ($translations as $t): ?>
                            <li>
                                <?= $this->Html->link($t->title, ['plugin' => 'Content', 'controller' => 'manage', 'action' => 'edit', $t->id]); ?> (<?= $t->language; ?>)
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </fieldset>
            <?= $this->Form->end(); ?>
        <?php else: ?>
            <div class="alert alert-danger">
                <?= __d('content', 'This content cannot be translated because there are no more available languages into which this content could be translated to.'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>