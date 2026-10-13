<?php
/**
 * Shared admin list-page helpers.
 * Helpers echo markup. They do not run queries.
 */

function ui_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ui_query(array $set = [], array $drop = []) {
    $query = $_GET;
    foreach ($drop as $key) {
        unset($query[$key]);
    }
    foreach ($set as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    $built = http_build_query($query);
    return $built === '' ? '?' : '?' . $built;
}

function renderPageHead($title, $subtitle, $actionHtml = '') {
    echo '<header class="list-head">';
    echo '<div class="list-head-copy">';
    echo '<h2 class="list-title">' . ui_h($title) . '</h2>';
    if ($subtitle !== '') {
        echo '<p class="list-subtitle">' . ui_h($subtitle) . '</p>';
    }
    echo '</div>';
    if ($actionHtml !== '') {
        echo '<div class="list-head-action">' . $actionHtml . '</div>';
    }
    echo '</header>';
}

function renderStatusChips(array $chips, $current) {
    echo '<nav class="list-chips" aria-label="Status">';
    foreach ($chips as $chip) {
        $value = (string) ($chip['value'] ?? '');
        $active = ((string) $current === $value);
        $href = ui_query(
            ['filter' => $value === '' ? null : $value],
            ['page']
        );
        echo '<a class="list-chip" href="' . ui_h($href) . '"' . ($active ? ' aria-current="true"' : '') . '>';
        echo '<span>' . ui_h($chip['label'] ?? $value) . '</span>';
        if (array_key_exists('count', $chip)) {
            echo '<span class="list-chip-count">' . (int) $chip['count'] . '</span>';
        }
        echo '</a>';
    }
    echo '</nav>';
}

function renderActiveFilters(array $chips) {
    if (!$chips) {
        return;
    }
    echo '<div class="list-active" aria-label="Active filters">';
    foreach ($chips as $chip) {
        echo '<a class="list-active-chip" href="' . ui_h($chip['href']) . '">';
        echo ui_h($chip['label']);
        echo ' <span aria-hidden="true">×</span>';
        echo '<span class="u-clip">Remove ' . ui_h($chip['label']) . '</span>';
        echo '</a>';
    }
    echo '</div>';
}

function renderListToolbar($searchName, $searchValue, $placeholder, $filtersHtml, $exportHtml, $clearHref, array $active = [], $searchId = 'search') {
    echo '<form class="list-toolbar" method="get" action="' . ui_h(basename($_SERVER['SCRIPT_NAME'] ?? '')) . '">';
    foreach ($_GET as $key => $value) {
        if ($key === 'search' || $key === 'page' || is_array($value)) {
            continue;
        }
        echo '<input type="hidden" name="' . ui_h($key) . '" value="' . ui_h($value) . '">';
    }
    echo '<label class="list-search">';
    echo '<span class="u-clip">Search</span>';
    echo '<i class="fas fa-search" aria-hidden="true"></i>';
    echo '<input type="search" id="' . ui_h($searchId) . '" name="' . ui_h($searchName) . '" value="' . ui_h($searchValue) . '" placeholder="' . ui_h($placeholder) . '" aria-label="Search">';
    echo '</label>';
    if ($filtersHtml !== '') {
        echo '<div class="list-toolbar-filters">' . $filtersHtml . '</div>';
    }
    echo '<div class="list-toolbar-actions">';
    echo $exportHtml;
    echo '<a class="btn btn-secondary" href="' . ui_h($clearHref) . '">Clear filters</a>';
    echo '<button class="btn btn-primary" type="submit">Search</button>';
    echo '</div>';
    echo '</form>';
    renderActiveFilters($active);
}

function renderBulkBar(array $statuses = [], $allowDelete = true, $allowExport = true) {
    echo '<div class="list-bulk" id="listBulk" hidden>';
    echo '<p class="list-bulk-count" id="listBulkSummary">0 selected</p>';
    echo '<div class="list-bulk-actions">';
    if ($statuses) {
        echo '<label class="list-bulk-status"><span class="u-clip">Change status</span>';
        echo '<select name="status" id="bulkStatus" form="listBulkForm">';
        echo '<option value="">Change status</option>';
        foreach ($statuses as $value => $label) {
            echo '<option value="' . ui_h($value) . '">' . ui_h($label) . '</option>';
        }
        echo '</select></label>';
        echo '<button type="button" class="btn btn-secondary btn-sm" id="bulkStatusBtn">Change status</button>';
    }
    if ($allowExport) {
        echo '<button type="button" class="btn btn-secondary btn-sm" id="bulkExportBtn">Export</button>';
    }
    if ($allowDelete) {
        echo '<button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn">Delete</button>';
    }
    echo '</div></div>';
}

function renderStatusBadge($status, $label = null) {
    $key = strtolower(trim((string) $status));
    $map = [
        'pending' => ['Pending', 'fas fa-clock', 'is-pending'],
        'reviewed' => ['Reviewed', 'fas fa-eye', 'is-reviewed'],
        'shortlisted' => ['Shortlisted', 'fas fa-star', 'is-shortlisted'],
        'rejected' => ['Rejected', 'fas fa-times', 'is-rejected'],
        'hired' => ['Hired', 'fas fa-user-check', 'is-success'],
        'waiting for interview' => ['Waiting for interview', 'fas fa-calendar-check', 'is-interview'],
        'blacklisted' => ['Blacklisted', 'fas fa-ban', 'is-danger'],
        'confirmed' => ['Confirmed', 'fas fa-check', 'is-success'],
        'cancelled' => ['Cancelled', 'fas fa-times', 'is-rejected'],
        'waitlisted' => ['Waitlisted', 'fas fa-clock', 'is-pending'],
        'completed' => ['Completed', 'fas fa-check', 'is-success'],
        'active' => ['Active', 'fas fa-check', 'is-success'],
        'inactive' => ['Inactive', 'fas fa-pause', 'is-neutral'],
        'open' => ['Open', 'fas fa-door-open', 'is-success'],
        'closed' => ['Closed', 'fas fa-door-closed', 'is-neutral'],
        'full' => ['Full', 'fas fa-users', 'is-reviewed'],
        'new' => ['New', 'fas fa-circle', 'is-pending'],
        'contacted' => ['Contacted', 'fas fa-phone', 'is-reviewed'],
        'qualified' => ['Qualified', 'fas fa-star', 'is-shortlisted'],
        'webinar' => ['Webinar', 'fas fa-video', 'is-interview'],
        'training' => ['Training', 'fas fa-chalkboard', 'is-reviewed'],
        'admin' => ['Admin', 'fas fa-user-shield', 'is-danger'],
        'manager' => ['Staff', 'fas fa-user-tie', 'is-reviewed'],
        'trainer' => ['Trainer', 'fas fa-id-badge', 'is-interview'],
        'user' => ['User', 'fas fa-user', 'is-neutral'],
    ];
    $item = $map[$key] ?? [ucwords($key !== '' ? $key : 'Unknown'), 'fas fa-circle', 'is-neutral'];
    $text = $label !== null && $label !== '' ? $label : $item[0];
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $key), '-');
    echo '<span class="status-badge ' . ui_h($item[2]) . ' status-' . ui_h($slug) . '">';
    echo '<i class="' . ui_h($item[1]) . '" aria-hidden="true"></i>';
    echo ui_h($text);
    echo '</span>';
}

function renderEmptyState($icon, $title, $message, $actionHref = '', $actionLabel = '') {
    echo '<div class="list-empty">';
    echo '<i class="' . ui_h($icon) . '" aria-hidden="true"></i>';
    echo '<h3>' . ui_h($title) . '</h3>';
    echo '<p>' . ui_h($message) . '</p>';
    if ($actionHref !== '' && $actionLabel !== '') {
        echo '<a class="btn btn-primary" href="' . ui_h($actionHref) . '">' . ui_h($actionLabel) . '</a>';
    }
    echo '</div>';
}

function renderPagination($page, $perPage, $total, $pageParam = 'page') {
    $page = max(1, (int) $page);
    $perPage = max(1, (int) $perPage);
    $total = max(0, (int) $total);
    $pages = max(1, (int) ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
    $to = min($total, $page * $perPage);
    $sizes = [10, 25, 50, 100];

    echo '<footer class="list-footer">';
    echo '<p class="list-range">Showing ' . $from . ' to ' . $to . ' of ' . $total . '</p>';
    echo '<form class="list-page-size" method="get" action="' . ui_h(basename($_SERVER['SCRIPT_NAME'] ?? '')) . '">';
    foreach ($_GET as $key => $value) {
        if ($key === 'per_page' || $key === $pageParam || is_array($value)) {
            continue;
        }
        echo '<input type="hidden" name="' . ui_h($key) . '" value="' . ui_h($value) . '">';
    }
    echo '<label>Rows <select name="per_page" aria-label="Rows per page" onchange="this.form.submit()">';
    foreach ($sizes as $size) {
        echo '<option value="' . $size . '"' . ($size === (int) $perPage ? ' selected' : '') . '>' . $size . '</option>';
    }
    echo '</select></label></form>';
    echo '<nav class="list-pager" aria-label="Pagination">';
    $prev = $page > 1 ? ui_query([$pageParam => $page - 1]) : '';
    $next = $page < $pages ? ui_query([$pageParam => $page + 1]) : '';
    if ($prev !== '') {
        echo '<a href="' . ui_h($prev) . '">Previous</a>';
    } else {
        echo '<span class="is-disabled">Previous</span>';
    }
    $start = max(1, $page - 2);
    $end = min($pages, $start + 4);
    $start = max(1, $end - 4);
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $page) {
            echo '<span aria-current="page">' . $i . '</span>';
        } else {
            echo '<a href="' . ui_h(ui_query([$pageParam => $i])) . '">' . $i . '</a>';
        }
    }
    if ($next !== '') {
        echo '<a href="' . ui_h($next) . '">Next</a>';
    } else {
        echo '<span class="is-disabled">Next</span>';
    }
    echo '</nav></footer>';
}

function renderRowActions(array $items) {
    echo '<div class="row-menu">';
    echo '<button type="button" class="row-menu-btn" aria-label="Row actions" aria-expanded="false" aria-haspopup="menu">';
    echo '<i class="fas fa-ellipsis-v" aria-hidden="true"></i>';
    echo '</button>';
    echo '<div class="row-menu-panel" role="menu" hidden>';
    foreach ($items as $item) {
        if (!empty($item['hidden'])) {
            continue;
        }
        $class = 'row-menu-item' . (!empty($item['danger']) ? ' is-danger' : '');
        $icon = ui_h($item['icon'] ?? 'fas fa-circle');
        $label = ui_h($item['label'] ?? 'Action');
        if (!empty($item['href'])) {
            echo '<a class="' . $class . '" role="menuitem" href="' . ui_h($item['href']) . '">';
        } else {
            $onclick = isset($item['onclick']) ? ' onclick="' . ui_h($item['onclick']) . '"' : '';
            echo '<button type="button" class="' . $class . '" role="menuitem"' . $onclick . '>';
        }
        echo '<i class="' . $icon . '" aria-hidden="true"></i> ' . $label;
        echo !empty($item['href']) ? '</a>' : '</button>';
    }
    echo '</div></div>';
}

function ui_page_size($default = 25) {
    $size = (int) ($_GET['per_page'] ?? $default);
    return in_array($size, [10, 25, 50, 100], true) ? $size : $default;
}
