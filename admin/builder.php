<?php
/**
 * Admin Builder Page
 * Form builder interface
 */

if (!defined('ABSPATH')) {
    exit;
}

$storage = new Form_Builder_Storage();
$builder = new Form_Builder_Builder();

// Get form ID from URL
$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

// If page is form-builder-new, set action to new
if (isset($_GET['page']) && $_GET['page'] === 'form-builder-new') {
    $action = 'new';
}

$form = null;
if ($form_id && $action === 'edit') {
    $form = $storage->get_form_by_id($form_id);
}

// If editing but form not found, redirect to list
if ($form_id && $action === 'edit' && !$form) {
    wp_redirect(admin_url('admin.php?page=form-builder'));
    exit;
}

$field_types = $builder->get_field_types();
?>

<div class="wrap form-builder-admin">
    <!-- Cache busting meta tags -->
    <script type="text/javascript">
        // Add cache busting meta tags
        if (typeof formBuilderAdmin !== 'undefined' && formBuilderAdmin.isDev) {
            var metaNoCache = document.createElement('meta');
            metaNoCache.setAttribute('http-equiv', 'Cache-Control');
            metaNoCache.setAttribute('content', 'no-cache, no-store, must-revalidate');
            document.head.appendChild(metaNoCache);
            
            var metaPragma = document.createElement('meta');
            metaPragma.setAttribute('http-equiv', 'Pragma');
            metaPragma.setAttribute('content', 'no-cache');
            document.head.appendChild(metaPragma);
            
            var metaExpires = document.createElement('meta');
            metaExpires.setAttribute('http-equiv', 'Expires');
            metaExpires.setAttribute('content', '0');
            document.head.appendChild(metaExpires);
            
            console.log('Cache busting meta tags added for development mode');
        }
    </script>
    
    <h1><?php echo $action === 'edit' ? __('Edit Form', 'form-builder-microsaas') : ($action === 'new' ? __('Add New Form', 'form-builder-microsaas') : __('Konstruct Form Builder', 'form-builder-microsaas')); ?></h1>
    
    <?php if ($action === 'list'): ?>
        <!-- Forms List -->
        <div class="form-builder-list">
            <div class="form-builder-list-actions">
                <a href="<?php echo admin_url('admin.php?page=form-builder-new'); ?>" class="button button-primary">
                    <?php _e('Add New Form', 'form-builder-microsaas'); ?>
                </a>
                <button type="button" id="import-form-btn" class="button button-secondary">
                    <?php _e('Import Form', 'form-builder-microsaas'); ?>
                </button>
            </div>
            
            <!-- Import Form Modal -->
            <div id="import-form-modal" class="form-builder-modal" style="display: none;">
                <div class="form-builder-modal-content">
                    <div class="form-builder-modal-header">
                        <h3><?php _e('Import Form', 'form-builder-microsaas'); ?></h3>
                        <button type="button" class="form-builder-modal-close">&times;</button>
                    </div>
                    <div class="form-builder-modal-body">
                        <p><?php _e('Select a JSON file exported from Form Builder to import:', 'form-builder-microsaas'); ?></p>
                        <input type="file" id="import-file-input" accept=".json" />
                        <div id="import-status" class="form-builder-import-status"></div>
                    </div>
                    <div class="form-builder-modal-footer">
                        <button type="button" id="import-form-submit" class="button button-primary" disabled>
                            <?php _e('Import', 'form-builder-microsaas'); ?>
                        </button>
                        <button type="button" class="button form-builder-modal-close">
                            <?php _e('Cancel', 'form-builder-microsaas'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Form Name', 'form-builder-microsaas'); ?></th>
                        <th><?php _e('Slug', 'form-builder-microsaas'); ?></th>
                        <th><?php _e('Pages', 'form-builder-microsaas'); ?></th>
                        <th><?php _e('Updated', 'form-builder-microsaas'); ?></th>
                        <th><?php _e('Actions', 'form-builder-microsaas'); ?></th>
                    </tr>
                </thead>
                <tbody id="forms-list">
                    <!-- Forms will be loaded via JavaScript -->
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <!-- Form Builder -->
        <div class="form-builder-editor">
            <div class="form-builder-header">
                <input type="text" id="form-name" class="form-builder-form-name" placeholder="<?php _e('Form Name', 'form-builder-microsaas'); ?>" value="<?php echo $form ? esc_attr($form['form_name']) : ''; ?>">
                <input type="text" id="form-slug" class="form-builder-form-slug" placeholder="<?php _e('Form Slug', 'form-builder-microsaas'); ?>" value="<?php echo $form ? esc_attr($form['form_slug']) : ''; ?>">
                <div class="form-builder-actions">
                    <?php if ($form && $form_id): ?>
                        <button type="button" id="copy-shortcode" class="button button-secondary copy-shortcode-btn" data-form-id="<?php echo esc_attr($form_id); ?>">
                            <?php _e('Copy Shortcode', 'form-builder-microsaas'); ?>
                        </button>
                    <?php endif; ?>
                    <button type="button" id="save-form" class="button button-primary"><?php _e('Save Form', 'form-builder-microsaas'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=form-builder'); ?>" class="button"><?php _e('Cancel', 'form-builder-microsaas'); ?></a>
                </div>
            </div>
            
            <div class="form-builder-content">
                <div class="form-builder-sidebar">
                    <h3><?php _e('Field Types', 'form-builder-microsaas'); ?></h3>
                    <div class="field-types-list">
                        <?php foreach ($field_types as $type => $label): ?>
                            <button type="button" class="field-type-btn" data-type="<?php echo esc_attr($type); ?>">
                                <?php echo esc_html($label); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    
                    <h3><?php _e('Pages', 'form-builder-microsaas'); ?></h3>
                    <div id="pages-list"></div>
                    <button type="button" id="add-page" class="button"><?php _e('Add Page', 'form-builder-microsaas'); ?></button>
                </div>
                
                <div class="form-builder-main">
                    <div id="pages-editor"></div>
                </div>
                
                <div class="form-builder-properties">
                    <h3><?php _e('Page Settings', 'form-builder-microsaas'); ?></h3>
                    <div id="page-properties"></div>
                </div>
            </div>
        </div>
        
        <script type="application/json" id="form-data">
            <?php echo json_encode($form ? $form['form_config'] : $builder->get_default_form()); ?>
        </script>
    <?php endif; ?>
    
    <!-- Notification Container -->
    <div id="form-builder-notifications" class="form-builder-notifications"></div>
    
    <!-- Confirmation Dialog Container -->
    <div id="form-builder-confirm-dialog" class="form-builder-confirm-dialog" style="display: none;">
        <div class="form-builder-confirm-content">
            <div class="form-builder-confirm-header">
                <h3 id="form-builder-confirm-title" class="form-builder-confirm-title">Confirm Action</h3>
            </div>
            <div class="form-builder-confirm-body">
                <p id="form-builder-confirm-message" class="form-builder-confirm-message">Are you sure?</p>
            </div>
            <div class="form-builder-confirm-footer">
                <button type="button" id="form-builder-confirm-cancel" class="button">Cancel</button>
                <button type="button" id="form-builder-confirm-ok" class="button button-primary">Confirm</button>
            </div>
        </div>
    </div>
</div>

