<?php

function plugin_knowledgeautonumber_get_translation($string, $language = null) {
    $translations = [
        'en_GB' => [
            'Knowledge Item Number' => 'Knowledge Item Number',
            'Automatically generated after saving' => 'Automatically generated after saving'
        ],
        'nl_NL' => [
            'Knowledge Item Number' => 'Kennisbanknummer',
            'Automatically generated after saving' => 'Automatisch gegenereerd na opslaan'
        ],
        'pl_PL' => [
            'Knowledge Item Number' => 'Numer pozycji wiedzy',
            'Automatically generated after saving' => 'Automatycznie generowane po zapisaniu'
        ],
        'de_DE' => [
            'Knowledge Item Number' => 'Wissensartikelnummer',
            'Automatically generated after saving' => 'Automatisch generiert nach dem Speichern'
        ],
        'fr_FR' => [
            'Knowledge Item Number' => 'Numéro d\'article de connaissance',
            'Automatically generated after saving' => 'Généré automatiquement après enregistrement'
        ],
        'es_ES' => [
            'Knowledge Item Number' => 'Número de artículo de conocimiento',
            'Automatically generated after saving' => 'Generado automáticamente después de guardar'
        ],
        'it_IT' => [
            'Knowledge Item Number' => 'Numero elemento conoscenza',
            'Automatically generated after saving' => 'Generato automaticamente dopo il salvataggio'
        ],
        'pt_PT' => [
            'Knowledge Item Number' => 'Número do item de conhecimento',
            'Automatically generated after saving' => 'Gerado automaticamente após salvar'
        ],
        'ru_RU' => [
            'Knowledge Item Number' => 'Номер элемента знаний',
            'Automatically generated after saving' => 'Автоматически генерируется после сохранения'
        ],
        'ja_JP' => [
            'Knowledge Item Number' => 'ナレッジアイテム番号',
            'Automatically generated after saving' => '保存後に自動生成'
        ],
        'zh_CN' => [
            'Knowledge Item Number' => '知识项目编号',
            'Automatically generated after saving' => '保存后自动生成'
        ],
        'ar_SA' => [
            'Knowledge Item Number' => 'رقم عنصر المعرفة',
            'Automatically generated after saving' => 'تم إنشاؤه تلقائيًا بعد الحفظ'
        ],
        'tr_TR' => [
            'Knowledge Item Number' => 'Bilgi Öğe Numarası',
            'Automatically generated after saving' => 'Kaydetmeden sonra otomatik oluşturulur'
        ],
        'sv_SE' => [
            'Knowledge Item Number' => 'Kunskapsartikelnummer',
            'Automatically generated after saving' => 'Automatiskt genereras efter sparande'
        ]
    ];

    // Bepaal de huidige taal van GLPI
    if ($language === null) {
        $language = $_SESSION['glpilanguage'] ?? 'en_GB';
    }

    return $translations[$language][$string] ?? $string;
}

function plugin_knowledgeautonumber_pre_item_add($item) {
    if ($item instanceof KnowbaseItem) {
        global $DB;
        
        try {
            // 1. Get next sequence number (with locking)
            $DB->query("SELECT last_number FROM glpi_plugin_knowledgeautonumber_sequence WHERE id = 1 FOR UPDATE");
            
            $iterator = $DB->request([
                'SELECT' => ['last_number'],
                'FROM' => 'glpi_plugin_knowledgeautonumber_sequence',
                'WHERE' => ['id' => 1],
                'LIMIT' => 1
            ]);
            
            $current_number = $iterator->count() > 0 ? (int)$iterator->current()['last_number'] : 0;
            $new_number = $current_number + 1;
            $kb_number = "KI-" . str_pad($new_number, 4, "0", STR_PAD_LEFT);
            
            // 2. Store all needed data in the item object
            $item->input['_auto_kb_number'] = $kb_number;
            $item->input['_auto_kb_sequence'] = $new_number;
            
            // 3. Update sequence immediately
            $DB->update('glpi_plugin_knowledgeautonumber_sequence', [
                'last_number' => $new_number
            ], ['id' => 1]);
            
            // 4. FORCE the number to be saved by adding it to fields
            $item->fields['kb_number'] = $kb_number;
            
            error_log("[KnowledgeAutoNumber] Pre-add: Assigned number $kb_number for new item");
            
        } catch (Exception $e) {
            error_log("[KnowledgeAutoNumber] Pre-add error: " . $e->getMessage());
            unset($item->input['_auto_kb_number']);
            unset($item->input['_auto_kb_sequence']);
        }
    }
}

function plugin_knowledgeautonumber_post_item_add($item) {
    if ($item instanceof KnowbaseItem && isset($item->input['_auto_kb_number'])) {
        global $DB;
        
        $item_id = $item->getID();
        $kb_number = $item->input['_auto_kb_number'];
        
        error_log("[KnowledgeAutoNumber] Post-add: Processing item $item_id with number $kb_number");
        
        try {
            // 1. DIRECT INSERT without checking existence first
            $result = $DB->insert('glpi_plugin_knowledgeautonumber_numbers', [
                'item_id' => $item_id,
                'kb_number' => $kb_number
            ]);
            
            if (!$result) {
                throw new Exception("Insert failed: " . $DB->error());
            }
            
            error_log("[KnowledgeAutoNumber] Successfully inserted KB number $kb_number for item $item_id");
            
            // 2. Verify by direct query
            $check = $DB->request([
                'SELECT' => ['id'],
                'FROM' => 'glpi_plugin_knowledgeautonumber_numbers',
                'WHERE' => ['item_id' => $item_id],
                'LIMIT' => 1
            ]);
            
            if ($check->count() === 0) {
                throw new Exception("Verification failed - no record found after insert");
            }
            
        } catch (Exception $e) {
            error_log("[KnowledgeAutoNumber] Post-add error for item $item_id: " . $e->getMessage());
            
            // Attempt to rollback sequence
            if (isset($item->input['_auto_kb_sequence'])) {
                $new_number = $item->input['_auto_kb_sequence'];
                $DB->update('glpi_plugin_knowledgeautonumber_sequence', [
                    'last_number' => $new_number - 1
                ], ['id' => 1]);
                error_log("[KnowledgeAutoNumber] Rolled back sequence to " . ($new_number - 1));
            }
        }
    }
}

function plugin_knowledgeautonumber_post_item_form($params) {
    $item = is_array($params) && isset($params['item']) ? $params['item'] : $params;

    plugin_knowledgeautonumber_number_all_items();

    if ($item instanceof KnowbaseItem) {
        global $DB;
        
        $kb_number = '';
        $item_id = $item->getID();
        
        // Check in this order:
        // 1. Newly generated number (not yet saved)
        if (isset($item->input['_auto_kb_number'])) {
            $kb_number = $item->input['_auto_kb_number'];
        } 
        // 2. Item fields (for immediate display after save)
        elseif (isset($item->fields['kb_number']) && !empty($item->fields['kb_number'])) {
            $kb_number = $item->fields['kb_number'];
        }
        // 3. Database (for existing items)
        elseif ($item_id > 0) {
            $iterator = $DB->request([
                'SELECT' => ['kb_number'],
                'FROM' => 'glpi_plugin_knowledgeautonumber_numbers',
                'WHERE' => ['item_id' => $item_id],
                'LIMIT' => 1
            ]);
            
            if ($iterator->count() > 0) {
                $kb_number = $iterator->current()['kb_number'];
            }
        }
        
        // Display the field
        $label = plugin_knowledgeautonumber_get_translation('Knowledge Item Number');
        echo "<div class='form-field row mb-2'>";
        echo "<label class='col-form-label col-sm-4'>$label</label>";
        echo "<div class='col-sm-8'>";
        
        if (!empty($kb_number)) {
            echo "<input type='text' class='form-control' value='".htmlspecialchars($kb_number, ENT_QUOTES)."' readonly>";
        } else {
            $placeholder = plugin_knowledgeautonumber_get_translation('Automatically generated after saving');
            echo "<input type='text' class='form-control' placeholder='".htmlspecialchars($placeholder, ENT_QUOTES)."' readonly>";
        }
        
        echo "</div></div>";
    }
}

function plugin_knowledgeautonumber_number_all_items() {
        global $DB;
    
        try {
            // Start a transaction
            $DB->beginTransaction();
            Toolbox::logInFile("knowledgeautonumber", "Transaction started for renumbering items from 1.");
    
            // Reset the sequence to 1
            $DB->update('glpi_plugin_knowledgeautonumber_sequence', [
                'last_number' => 0
            ], ['id' => 1]);
    
            Toolbox::logInFile("knowledgeautonumber", "Sequence reset to 1.");
    
            // Get all the items in the knowledge base (this assumes the table is called 'glpi_knowbaseitems')
            $items = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_knowbaseitems',
                'LIMIT'  => 1000  // Fetch in chunks if there are too many items
            ]);
    
            if ($items->count() == 0) {
                Toolbox::logInFile("knowledgeautonumber", "No items found to renumber.");
                $DB->commit();
                return;
            }
    
            Toolbox::logInFile("knowledgeautonumber", "Found " . $items->count() . " items to renumber.");
    
            // Loop through each item and renumber
            $current_number = 0; // Start from 0 as we'll increment to 1
            foreach ($items as $item) {
                $item_id = $item['id'];
    
                // Increment the sequence number
                $current_number++;
    
                // Generate the KB number
                $kb_number = "KI-" . str_pad($current_number, 4, "0", STR_PAD_LEFT);
    
                Toolbox::logInFile("knowledgeautonumber", "Generating KB number for item_id $item_id: $kb_number");
    
                // Check if the entry already exists in the glpi_plugin_knowledgeautonumber_numbers table
                $existing_entry = $DB->request([
                    'SELECT' => ['item_id'],
                    'FROM'   => 'glpi_plugin_knowledgeautonumber_numbers',
                    'WHERE'  => ['item_id' => $item_id],
                    'LIMIT'  => 1
                ]);
    
                if ($existing_entry->count() > 0) {
                    // If it exists, update the existing entry
                    Toolbox::logInFile("knowledgeautonumber", "Updating existing KB number for item_id $item_id.");
                    $DB->update('glpi_plugin_knowledgeautonumber_numbers', [
                        'kb_number' => $kb_number
                    ], ['item_id' => $item_id]);
                } else {
                    // Otherwise, insert a new record
                    Toolbox::logInFile("knowledgeautonumber", "Inserting new KB number for item_id $item_id.");
                    $DB->insert('glpi_plugin_knowledgeautonumber_numbers', [
                        'item_id'   => $item_id,
                        'kb_number' => $kb_number
                    ]);
                }
    
                // Update the sequence table to the latest number
                $DB->update('glpi_plugin_knowledgeautonumber_sequence', [
                    'last_number' => $current_number
                ], ['id' => 1]);
            }
    
            // Commit the transaction after all items have been renumbered
            $DB->commit();
            Toolbox::logInFile("knowledgeautonumber", "Transaction committed. All items have been renumbered starting from KI-0001.");
    
        } catch (Exception $e) {
            // Rollback if something goes wrong
            $DB->rollBack();
            Toolbox::logInFile("sql-errors", "Error renumbering all items from 1: " . $e->getMessage());
        }
    }
    