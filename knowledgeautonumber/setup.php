<?php

function plugin_version_knowledgeautonumber() {
    return [
        'name'           => 'Knowledge AutoNumber',
        'version'        => '1.0.8',
        'author'         => 'Destiny_fur from MagSec',
        'license'        => 'GPLv3',
        'minGlpiVersion' => '10.0.0',
        'maxGlpiVersion' => '10.0.99',
        'homepage'       => 'https://magsec.ml',
        'icon'           => 'pics/icon.png'
    ];
}

function plugin_init_knowledgeautonumber() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['knowledgeautonumber'] = true;

    $PLUGIN_HOOKS['pre_item_add']['knowledgeautonumber'] = [
        'KnowbaseItem' => 'plugin_knowledgeautonumber_pre_item_add'
    ];

    $PLUGIN_HOOKS['post_item_add']['knowledgeautonumber'] = [
        'KnowbaseItem' => 'plugin_knowledgeautonumber_post_item_add'
    ];

    $PLUGIN_HOOKS['post_item_form']['knowledgeautonumber'] = 'plugin_knowledgeautonumber_post_item_form';

    $PLUGIN_HOOKS['init_session']['knowledgeautonumber'] = 'plugin_knowledgeautonumber_init_session';

    $PLUGIN_HOOKS['display']['knowledgeautonumber'] = 'plugin_knowledgeautonumber_display_kb_number_on_view';

    $PLUGIN_HOOKS['add_css']['knowledgeautonumber'] = 'knowledgeautonumber.css';
}

function plugin_knowledgeautonumber_init_session() {
    if (Session::getLoginUserID()) {
        Plugin::loadLang('knowledgeautonumber');
    }
}

// Plugin installation (unchanged from your original)
function plugin_knowledgeautonumber_install() {
    global $DB;
    
    $tables = [
        'glpi_plugin_knowledgeautonumber_sequence' => [
            'cols' => [
                'id' => 'INT UNSIGNED NOT NULL PRIMARY KEY',
                'last_number' => 'INT UNSIGNED NOT NULL DEFAULT 0'
            ],
            'init' => ['id' => 1, 'last_number' => 0]
        ],
        'glpi_plugin_knowledgeautonumber_numbers' => [
            'cols' => [
                'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
                'item_id' => 'INT UNSIGNED NOT NULL',
                'kb_number' => 'VARCHAR(10) NOT NULL',
            ],
            'keys' => [
                'UNIQUE KEY (`item_id`)',
                'FOREIGN KEY (`item_id`) REFERENCES `glpi_knowbaseitems`(`id`) ON DELETE CASCADE'
            ]
        ]
    ];
    
    foreach ($tables as $table => $def) {
        if (!$DB->tableExists($table)) {
            $cols = [];
            foreach ($def['cols'] as $name => $type) {
                $cols[] = "`$name` $type";
            }
            
            $sql = "CREATE TABLE `$table` (" . implode(',', $cols);
            if (!empty($def['keys'])) {
                $sql .= ',' . implode(',', $def['keys']);
            }
            $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $DB->query($sql) or die("Failed to create $table: " . $DB->error());
            
            if (isset($def['init'])) {
                $DB->insert($table, $def['init']);
            }
        }
    }

    // Initialize sequence if needed
    if (!$DB->request(['FROM' => 'glpi_plugin_knowledgeautonumber_sequence'])->count()) {
        $DB->insert('glpi_plugin_knowledgeautonumber_sequence', [
            'id' => 1,
            'last_number' => 0
        ]);
    }

    // Assign numbers to existing items
    $iterator = $DB->request([
        'SELECT' => ['glpi_knowbaseitems.id'],
        'FROM' => 'glpi_knowbaseitems',
        'LEFT JOIN' => [
            'glpi_plugin_knowledgeautonumber_numbers' => [
                'FKEY' => [
                    'glpi_knowbaseitems' => 'id',
                    'glpi_plugin_knowledgeautonumber_numbers' => 'item_id'
                ]
            ]
        ],
        'WHERE' => ['glpi_plugin_knowledgeautonumber_numbers.item_id' => null]
    ]);

    if ($iterator->count() > 0) {
        $DB->beginTransaction();
        try {
            $seq = $DB->request([
                'SELECT' => ['last_number'],
                'FROM' => 'glpi_plugin_knowledgeautonumber_sequence',
                'WHERE' => ['id' => 1]
            ])->current();
            
            $next_number = (int)($seq['last_number'] ?? 0) + 1;

            foreach ($iterator as $row) {
                $kb_number = "KI-" . str_pad($next_number, 4, "0", STR_PAD_LEFT);
                $DB->insert('glpi_plugin_knowledgeautonumber_numbers', [
                    'item_id'   => $row['id'],
                    'kb_number' => $kb_number
                ]);
                $next_number++;
            }

            $DB->update('glpi_plugin_knowledgeautonumber_sequence', [
                'last_number' => $next_number - 1
            ], ['id' => 1]);

            $DB->commit();
        } catch (Exception $e) {
            $DB->rollback();
            Toolbox::logInFile("sql-errors", "Error assigning existing kb_numbers: " . $e->getMessage());
        }
    }

    return true;
}

// Plugin uninstallation (unchanged from your original)
function plugin_knowledgeautonumber_uninstall() {
    global $DB;

    $tables = [
        'glpi_plugin_knowledgeautonumber_numbers',
        'glpi_plugin_knowledgeautonumber_sequence'
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->query("DROP TABLE IF EXISTS `$table`");
        }
    }

    return true;
}