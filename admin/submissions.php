<?php
/**
 * Admin Submissions Page
 * Form submissions dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

$storage = new Form_Builder_Storage();

// Get filter parameters
$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Get all forms for filter dropdown
$forms = $storage->get_all_forms();
$forms_list = $forms['forms'];

// Build query
global $wpdb;
$query = "
    SELECT s.*, f.form_name, f.form_slug
    FROM {$wpdb->prefix}form_builder_submissions s
    LEFT JOIN {$wpdb->prefix}form_builder_forms f ON s.form_id = f.id
    WHERE 1=1
";

$where_conditions = array();
$where_values = array();

if ($form_id > 0) {
    $where_conditions[] = "s.form_id = %d";
    $where_values[] = $form_id;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(s.created_at) >= %s";
    $where_values[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(s.created_at) <= %s";
    $where_values[] = $date_to;
}

if (!empty($search)) {
    $where_conditions[] = "(f.form_name LIKE %s OR s.submission_uuid LIKE %s)";
    $where_values[] = '%' . $wpdb->esc_like($search) . '%';
    $where_values[] = '%' . $wpdb->esc_like($search) . '%';
}

if (!empty($where_conditions)) {
    $query .= " AND " . implode(" AND ", $where_conditions);
}

$query .= " ORDER BY s.created_at DESC";

// Get total count for pagination
$count_query = str_replace("SELECT s.*, f.form_name, f.form_slug", "SELECT COUNT(*) as total", $query);
$total_submissions = $wpdb->get_var($wpdb->prepare($count_query, $where_values));

// Pagination
$per_page = 25;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

$query .= " LIMIT %d OFFSET %d";
$where_values[] = $per_page;
$where_values[] = $offset;

$submissions = $wpdb->get_results($wpdb->prepare($query, $where_values), ARRAY_A);

// Decode form data
foreach ($submissions as &$submission) {
    if ($submission['form_data']) {
        $submission['form_data'] = json_decode($submission['form_data'], true);
    }
}

// Calculate pagination info
$total_pages = ceil($total_submissions / $per_page);
?>

<div class="wrap form-builder-admin">
    <h1><?php _e('Konstruct Form Builder - Submissions', 'form-builder-microsaas'); ?></h1>

    <!-- Filters -->
    <div class="form-builder-filters" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #e5e7eb; border-radius: 8px;">
        <form method="GET" action="">
            <input type="hidden" name="page" value="form-builder-submissions">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                <div>
                    <label for="form_id" style="display: block; margin-bottom: 5px; font-weight: 500;">Filter by Form:</label>
                    <select name="form_id" id="form_id" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                        <option value="">All Forms</option>
                        <?php foreach ($forms_list as $form): ?>
                            <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($form_id, $form['id']); ?>>
                                <?php echo esc_html($form['form_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="date_from" style="display: block; margin-bottom: 5px; font-weight: 500;">Date From:</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>"
                           style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                </div>

                <div>
                    <label for="date_to" style="display: block; margin-bottom: 5px; font-weight: 500;">Date To:</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>"
                           style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                </div>

                <div>
                    <label for="search" style="display: block; margin-bottom: 5px; font-weight: 500;">Search:</label>
                    <input type="text" name="search" id="search" value="<?php echo esc_attr($search); ?>"
                           placeholder="Form name or UUID..."
                           style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="button button-primary" style="padding: 8px 16px;">
                        <?php _e('Filter', 'form-builder-microsaas'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=form-builder-submissions'); ?>" class="button" style="padding: 8px 16px;">
                        <?php _e('Clear', 'form-builder-microsaas'); ?>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Stats Cards -->
    <div class="form-builder-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <h3 style="margin: 0 0 10px 0; font-size: 2em;"><?php echo number_format($total_submissions); ?></h3>
            <p style="margin: 0; opacity: 0.9;">Total Submissions</p>
        </div>

        <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <h3 style="margin: 0 0 10px 0; font-size: 2em;"><?php echo count($forms_list); ?></h3>
            <p style="margin: 0; opacity: 0.9;">Active Forms</p>
        </div>

        <?php
        // Get today's submissions
        $today_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}form_builder_submissions
            WHERE DATE(created_at) = %s
        ", date('Y-m-d')));
        ?>
        <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
            <h3 style="margin: 0 0 10px 0; font-size: 2em;"><?php echo number_format($today_count); ?></h3>
            <p style="margin: 0; opacity: 0.9;">Today's Submissions</p>
        </div>
    </div>

    <!-- Submissions Table -->
    <div class="form-builder-table-container" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
        <table class="wp-list-table widefat fixed striped" style="border: none; margin: 0;">
            <thead style="background: #f9fafb;">
                <tr>
                    <th style="padding: 15px; font-weight: 600; color: #374151;">ID</th>
                    <th style="padding: 15px; font-weight: 600; color: #374151;">Form</th>
                    <th style="padding: 15px; font-weight: 600; color: #374151;">Submission UUID</th>
                    <th style="padding: 15px; font-weight: 600; color: #374151;">Data Preview</th>
                    <th style="padding: 15px; font-weight: 600; color: #374151;">Page</th>
                    <th style="padding: 15px; font-weight: 600; color: #374151;">Status</th>
                    <th style="padding: 15px; font-weight: 600; color: #374151;">Submitted</th>
                    <th style="padding: 15px; font-weight: 600; color: #374151;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                            <div style="font-size: 1.1em; margin-bottom: 10px;">📭 No submissions found</div>
                            <div>Try adjusting your filters or check if forms are being submitted.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($submissions as $submission): ?>
                        <tr style="border-bottom: 1px solid #f3f4f6;">
                            <td style="padding: 15px;"><?php echo esc_html($submission['id']); ?></td>
                            <td style="padding: 15px;">
                                <div style="font-weight: 500; color: #1f2937;"><?php echo esc_html($submission['form_name'] ?: 'Unknown Form'); ?></div>
                                <div style="font-size: 0.85em; color: #6b7280;"><?php echo esc_html($submission['form_slug']); ?></div>
                            </td>
                            <td style="padding: 15px;">
                                <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;"><?php echo esc_html($submission['submission_uuid']); ?></code>
                            </td>
                            <td style="padding: 15px;">
                                <?php
                                $data_preview = '';
                                if (is_array($submission['form_data'])) {
                                    $fields = array_slice(array_keys($submission['form_data']), 0, 3);
                                    $data_preview = implode(', ', array_map(function($field) use ($submission) {
                                        $value = $submission['form_data'][$field];
                                        if (is_array($value)) {
                                            // If file meta, show filename
                                            if (isset($value['name'])) {
                                                $value = $value['name'];
                                            } else {
                                                $value = implode(', ', $value);
                                            }
                                        }
                                        return $field . ': ' . (is_string($value) && strlen($value) > 20 ? substr($value, 0, 20) . '...' : (is_string($value) ? $value : ''));
                                    }, $fields));
                                }
                                ?>
                                <div style="font-size: 0.9em; color: #374151;"><?php echo esc_html($data_preview ?: 'No data'); ?></div>
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <span style="background: #e5e7eb; color: #374151; padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 500;">
                                    <?php echo esc_html($submission['page_number']); ?>
                                </span>
                            </td>
                            <td style="padding: 15px;">
                                <?php if ($submission['delivered']): ?>
                                    <span style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 500;">✅ Delivered</span>
                                <?php else: ?>
                                    <span style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 500;">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px;">
                                <div style="font-size: 0.9em; color: #374151;"><?php echo esc_html(date('M j, Y', strtotime($submission['created_at']))); ?></div>
                                <div style="font-size: 0.8em; color: #6b7280;"><?php echo esc_html(date('g:i A', strtotime($submission['created_at']))); ?></div>
                            </td>
                            <td style="padding: 15px;">
                                <button type="button" class="button button-small view-submission"
                                        data-submission='<?php echo esc_attr(json_encode($submission)); ?>'
                                        style="padding: 4px 8px; font-size: 0.8em;">
                                    👁️ View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="form-builder-pagination" style="margin-top: 20px; text-align: center;">
            <?php
            $base_url = add_query_arg(array(
                'page' => 'form-builder-submissions',
                'form_id' => $form_id,
                'date_from' => $date_from,
                'date_to' => $date_to,
                'search' => $search,
            ), admin_url('admin.php'));

            echo paginate_links(array(
                'base' => $base_url . '%_%',
                'format' => '&paged=%#%',
                'current' => $current_page,
                'total' => $total_pages,
                'prev_text' => '‹ Previous',
                'next_text' => 'Next ›',
                'type' => 'plain',
            ));
            ?>
        </div>
    <?php endif; ?>
</div>

<!-- Submission Detail Modal -->
<div id="submission-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 700px; width: 90%; max-height: 80%; overflow-y: auto;">
        <div style="position: relative; padding-right: 32px; margin-bottom: 12px;">
            <h3 style="margin: 0; font-size: 24px;">Submission Details</h3>
            <button type="button" id="close-modal" aria-label="Close" title="Close" style="position: absolute; right: 0; top: 0; background: none; border: none; font-size: 24px; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <div id="submission-content"></div>
    </div>
</div>

<style>
.form-builder-table-container .wp-list-table th,
.form-builder-table-container .wp-list-table td {
    border: none !important;
}

.form-builder-table-container .wp-list-table tbody tr:hover {
    background-color: #f9fafb !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    // View submission details
    $('.view-submission').on('click', function() {
        const submission = $(this).data('submission');

        let content = '<div style="margin-bottom: 20px;">';
        content += '<strong>Submission ID:</strong> ' + submission.id + '<br>';
        content += '<strong>Form:</strong> ' + (submission.form_name || 'Unknown') + '<br>';
        content += '<strong>UUID:</strong> <code style="word-break: break-all;">' + submission.submission_uuid + '</code><br>';
        content += '<strong>Page:</strong> ' + submission.page_number + '<br>';
        content += '<strong>Status:</strong> ' + (submission.delivered ? '✅ Delivered' : '⏳ Pending') + '<br>';
        content += '<strong>Submitted:</strong> ' + new Date(submission.created_at).toLocaleString() + '<br>';
        content += '</div>';
        content += '<div style="border-top: 1px solid #e5e7eb; padding-top: 15px; margin-top: 15px;"></div>';

        if (submission.form_data && typeof submission.form_data === 'object') {
            content += '<h4 style="margin-top: 20px; margin-bottom: 15px;">📋 Form Data:</h4>';
            content += '<div style="background: #f9fafb; padding: 15px; border-radius: 6px;">';
            
            let fieldIndex = 0;
            for (const [key, value] of Object.entries(submission.form_data)) {
                if (fieldIndex > 0) {
                    content += '<div style="border-top: 1px solid #e5e7eb; margin: 12px 0; padding-top: 12px;"></div>';
                }
                content += '<div style="margin-bottom: 12px;">';
                content += '<strong style="color: #0071e3; display: block; margin-bottom: 4px;">' + key + '</strong>';
                content += '<div style="color: #374151; word-break: break-word; font-family: monospace; font-size: 13px; padding: 6px 8px; background: white; border-radius: 4px; border-left: 3px solid #0071e3;">';
                if (Array.isArray(value)) {
                    content += '['  + value.map(v => '<span style="background: #e8eeff; padding: 2px 6px; border-radius: 3px; margin-right: 4px; display: inline-block;">' + (typeof v === 'string' ? v.substring(0, 100) : String(v)) + '</span>').join(' ') + ']';
                } else if (value && typeof value === 'object' && value.url) {
                    const label = value.name || 'Download file';
                    content += '<a href="' + value.url + '" target="_blank" rel="noopener" class="button">' + label + '</a>';
                } else {
                    content += typeof value === 'string' ? value.substring(0, 200) : String(value);
                }
                content += '</div>';
                content += '</div>';
                fieldIndex++;
            }
            content += '</div>';
        }

        $('#submission-content').html(content);
        $('#submission-modal').show();
    });

    // Close modal
    $('#close-modal').on('click', function() {
        $('#submission-modal').hide();
    });

    $(document).on('click', function(e) {
        if ($(e.target).is('#submission-modal')) {
            $('#submission-modal').hide();
        }
    });

});
</script>
