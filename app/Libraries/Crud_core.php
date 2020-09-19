<?php
namespace App\Libraries;

use CodeIgniter\HTTP\RequestInterface;

class Template
{

    protected $schema, // table schema
$base = null, // prefix uri or parrent controller.
$action = 'add', // determine create or update // default is create (add)
$table, // string
$table_title, // string
$form_title_add, // string
$form_title_update, // string
$form_submit, // string
$form_submit_update, // string
$fields = [], // array of field options: (type, required, label),
$id, // primary key value
$data, // array of form or table data
$id_field, // primary key field
$current_values, // will get current form values before updating
$db, // db connection instance
$model, // db connection instance
$request, $files = [], $seq = 1, // when current_values() is executed all fields that have type 'file' or 'files' will be stored here
$config, $multipart = false, $options = [
        'Label' => null,
        'Helper' => null,
        'Required' => false,
        'Table' => true,
        'Form' => true,
        'Class' => null,
        'Only_edit' => false,
        'Only_add' => false,
        'Type' => 'text',
        'Relation' => null,
        'Key' => null,
        'Default' => null,
        'Extra' => null,
        'Field' => null,
        'Path' => null,
        'Inner_class' => null,
        'Is_image' => null,
        'Max_size' => null,
        'Ext_in' => null,
        'Wrapper_start' => null,
        'Wrapper_end' => null,
        'Wrapper_item_start' => null,
        'Wrapper_item_end' => null,
        'Show_file_names' => null,
        'Placeholder' => null,
        'Delete_callback' => null,
        'Delete_file' => null,
        'Delete_button_class' => null,
        'callback' => null,
        'Values' => null
    ], $rel_options = [
        'Save_table' => null,
        'Parent_field' => null,
        'Child_field' => null,
        'Inner_class' => null,
        'Table' => null,
        'Primary_key' => 'id',
        'Display' => null,
        'Order_by' => [
            'id',
            'ASC'
        ],
        'Order' => 'ASC'
    ], $file_options = [
        'Label' => null,
        'Type' => null,
        'Path' => null,
        'Inner_class' => null,
        'Is_image' => null,
        'Max_size' => null,
        'Ext_in' => null,
        'Wrapper_start' => null,
        'Wrapper_end' => null,
        'Wrapper_item_start' => null,
        'Wrapper_item_end' => null,
        'Show_file_names' => null,
        'Placeholder' => null,
        'Delete_callback' => null,
        'Delete_file' => null,
        'Delete_button_class' => null
    ], $validator = false;

    function __construct($params, RequestInterface $request)
    {
        $this->request = $request;
        $this->table = $table = $params['table'];
        $this->db = db_connect();
        $this->model = model('App\Models\\' . ucfirst($table))->builder();
        $this->schema = $this->schema();
        $this->table_title = _r_lang(isset($params['table_title']) ? $params['table_title'] : 'all-items');
        $this->form_submit = _r_lang((isset($params['form_submit']) ? $params['form_submit'] : 'submit'));
        $this->form_title_update = _r_lang((isset($params['form_title_update']) ? $params['form_title_update'] : 'update-item'));
        $this->form_submit_update = _r_lang((isset($params['form_submit_update']) ? $params['form_submit_update'] : 'update'));
        $this->form_title_add = _r_lang((isset($params['form_title_add']) ? $params['form_title_add'] : 'create-item'));
        // Field options
        $this->config = config('Template');
        if (isset($params['fields']) && $params['fields']) {
            $this->fields = $params['fields'];
            foreach ($this->fields as $key => $field) {
                
                // Adding custom fields to schema for relational table
                if (isset($field['relation']) && isset($field['relation']['save_table'])) {
                    $newSchema = [
                        'Field' => $key,
                        'Type' => 'text',
                        'Key' => '',
                        'Default' => '',
                        'Extra' => 'other_table'
                    ];
                    $this->schema[] = (object) $newSchema;
                }
                
                // Adding custom fields to schema for relational table for files
                if (isset($field['files_relation']) && isset($field['files_relation']['files_table'])) {
                    $newSchema = [
                        'Field' => $key,
                        'Type' => 'text',
                        'Key' => '',
                        'Default' => '',
                        'Extra' => 'file_table'
                    ];
                    $this->schema[] = (object) $newSchema;
                }
            }
        }
        
        // Base uri
        if (isset($params['base']) && $params['base']) {
            $this->base = $params['base'];
        }
        
        // Check if form contains file fields
        $this->multipart = $this->formHasFileFields();
        
        // Show MySQL schema
        if (isset($params['dev']) && $params['dev']) {
            echo "<pre>";
            print_r($this->schema);
            echo "</pre>";
        }
        $this->setFields();
        helper('form');
    }

    protected function setFields()
    {
        $f = [];
        $fields = $this->fields;
        $schema = $this->schema;
        foreach ($schema as $c) {
            if (key_exists($c->Field, $fields)) {
                $f[$c->Field] = array_merge(array_change_key_case((array) $c), array_change_key_case($fields[$c->Field]));
                unset($fields[$c->Field]);
            } else {
                $f[$c->Field] = array_change_key_case((array) $c);
            }
            if (in_array($c->Field, $this->config->unset)) {
                $f[$c->Field]['type'] = 'unset';
            }
        }
        $f = array_merge($f, $fields);
        $f = array_change_key_case($f);
        $f = array_map(function ($a) {
            if (! isset($a['index'])) {
                $a['index'] = 100;
            }
            return $a;
        }, $f);
        uasort($f, function ($a, $b) {
            return $a['index'] == $b['index'] ? 0 : ($a['index'] <=> $b['index']);
        });
        $this->schema = $this->fields = $f;
        return $this;
        // foreach ($schema as $field) {
        // $field = (array) $field;
        // if (key_exists($field['Field'], $fields)) {
        // $f[$field['Field']] = array_merge($this->options, $field, $fields[$field['Field']]);
        // (! is_null($f[$field['Field']]['Relation'])) ? array_merge($this->rel_options, $fields[$field['Field']]) : null;
        // } else {
        // $f[$field['Field']] = array_merge($this->options, $field);
        // }
        // $f[$field['Field']]['Type'] = $this->get_field_type($f[$field['Field']]);
        // if (in_array($field['Field'], $this->config->unset)) {
        // $f[$field['Field']]['Type'] = 'unset';
        // }
        // $f[$field['Field']]['Label'] = $this->get_label($f[$field['Field']]);
        // }
        // // var_dump($f);
        // $f = array_merge($fields,$f);
        // return $this->schema = $this->fields = $f;
    }

    function setData($data = [])
    {
        $this->data = $data;
        return $this;
    }

    function setValues()
    {
        $this->id_field = $this->get_primary_key_field_name();
        
        $this->current_values = $item = $this->data;
        if (! $item) {
            $this->flash('warning', 'The record does not exist');
            return false;
        }
        
        foreach ($this->fields as $field => $options) {
            
            if (isset($options['type']) && $options['type'] == 'file') {
                $this->files[$field] = $item->{$field};
            }
            
            if (isset($options['type']) && $options['type'] == 'files') {
                
                $fileTable = $options['files_relation']['files_table'];
                $where = [
                    $options['files_relation']['parent_field'] => $id
                ];
                $files = $this->model->getAnyItems($fileTable, $where);
                $this->files[$field] = $files;
            }
        }
        
        $this->id = $this->current_values[$this->id_field];
        $this->action = 'edit';
        return $this;
    }

    function table()
    {
        // $root_url = $this->base . '/' . $this->table;
        return $this->items_table();
    }

    function form()
    {
        return $this->parent_form();
    }

    protected function parent_form()
    {
        $form = '';
        $form .= '<div class="card card-primary">
            <div class="card-header">
              <h3 class="card-title">' . ($this->action == 'add' ? $this->form_title_add : $this->form_title_update) . '</h3>
              </div>';
        if ($this->multipart) {
            $form .= form_open_multipart('/' . $this->base . '/' . $this->table . '/' . ($this->action == 'add' ? 'save' : 'save/' . $this->id)) . '<div class="card-body">';
        } else {
            $form .= form_open('/' . $this->base . '/' . $this->table . '/' . ($this->action == 'add' ? 'save' : 'save/' . $this->id)) . '<div class="card-body">';
        }
        $form .= '<div class="row">';
        
        $fields = $this->fields;
        foreach ($this->fields as $name => $f) {
            
            if (isset($f['extra']) && $f['extra'] == 'auto_increment') {
                continue;
            }
            
            if (isset($f['only_edit']) && $f['only_edit'] && $this->action == 'add') {
                continue;
            }
            
            if (isset($f['only_add']) && $f['only_add'] && $this->action == 'edit') {
                continue;
            }
            
            if ((isset($f['type']) && $f['type'] == 'unset') or (isset($f['form']) && $f['form'] == 'none')) {
                continue;
            }
            if (key_exists($name, $this->config->predefined)) {
                $val = call_user_func($this->config->predefined[$name]);
                $form .= '<input type="hidden" name="' . $this->table . '[' . $name . ']" value="' . $val . '">';
                continue;
            }
            
            $label = $this->get_label($f);
            $field_type = $this->get_field_type($f);
            
            if ($field_type == 'enum' && ! isset($f['values'])) {
                preg_match("/^enum\(\'(.*)\'\)$/", $f['type'], $matches);
                $f['values'] = explode("','", $matches[1]);
                $field_type = 'select';
            }
            // Check if relation table is set for the field
            if (isset($f['relation'])) {
                
                if (is_string($f['relation'])) {
                    $rel['table'] = $f['relation'];
                    $model = model('App\Models\\' . ucfirst($rel['table']));
                    $res = $model->findAll();
                    $f['values'] = $res;
                } elseif (isset($f['relation']) && is_array($f['relation'])) {
                    $rel = $f['relation'];
                    $display_val = '';
                    if (! isset($rel['display'])) {
                        $rel['display'] = 'all';
                    }
                    if (! isset($rel['order'])) {
                        $rel['order'] = 'id ASC';
                    }
                    $where = [];
                    if (isset($rel['where'])) {
                        $where[] = $rel['where'];
                    }
                    $model = model('App\Models\\' . ucfirst($rel['table']))->where($where)->orderBy($rel['order']);
                    $res = $model->findAll();
                    // echo model('App\Models\\' . ucfirst($rel['table']))->getLastQuery();
                    if (is_array($rel['display'])) {
                        foreach ($rel['display'] as $display) {
                            if (! key_exists($display, $res)) {
                                continue;
                            }
                            $display_val .= $res[$display] . ',';
                        }
                    } elseif (is_string($rel['display'])) {
                        if ($rel['display'] == 'all') {
                            $display_val = $res;
                        } else {
                            $display_val = $res[$rel['display']] ?? null;
                        }
                    }
                    $f['values'] = $res;
                }
            }
            
            $field_values = $f['values'] ?? null;
            
            $field_method = 'field_' . $field_type;
            
            // Checking if helper text is set for this field
            $helperText = '';
            if (isset($f['helper']))
                $helperText = '<small class="form-text text-muted">' . $f['helper'] . '</small>';
            
            $class = "col-sm-12";
            if (isset($f['class']))
                $class = $f['class'];
            
            $hidden = false;
            if (isset($f['type']) && $f['type'] == 'hidden')
                $hidden = true;
            else {
                $form .= "<div class='$class'><div class='form-group'>";
            }
            
            // execute appropriate function
            $form .= $this->{$field_method}($name, $label, $f, $field_values, $class);
            if (! $hidden) {
                $form .= "$helperText</div></div>";
            }
        }
        
        $form .= '</div></div><div class="card-footer">
        <button type="submit" class="btn btn-primary">' . ($this->action == 'add' ? $this->form_submit : $this->form_submit_update) . '</button>
        </div>' . form_close() . '</div>';
        
        if ($this->multipart) {
            $form .= '<script type="text/javascript">
        $(document).ready(function () {
        bsCustomFileInput.init();
        });
        </script>';
        }
        
        return $form;
    }

    protected function get_label($field, $default_label = 'Unkown')
    {
        
        // When generating error labels, because $field is not the same object from Schema
        if (! isset($field['type']))
            return _r_lang($field['label'] ?? ucfirst(str_replace('-', ' ', $default_label)));
        
        // return ucfirst($field);
        
        // When generating form labels
        if (isset($field['label'])) {
            return _r_lang($field['label']);
        } else {
            return _r_lang(ucfirst(str_replace('-', ' ', ($field['field'] ?? $default_label))));
        }
        // return (isset($this->fields[$field['Field']]['label']) ? $this->fields[$field['Field']]['label'] : ucfirst(str_replace('_', ' ', $field['Field'])));
    }

    protected function get_field_type($field)
    {
        $type = $field['type'] ?? 'varchar';
        if (strpos($type, 'enum') !== FALSE) {
            return 'checkbox';
        } elseif (strpos($type, 'simple_dropdown') !== FALSE) {
            return 'simple_dropdown';
        } elseif (strpos($type, 'decimal') !== FALSE) {
            return 'currency';
        } elseif (strpos($type, 'checkbox') !== FALSE) {
            return 'checkbox';
        } elseif (strpos($type, 'radio') !== FALSE) {
            return 'radio';
        } elseif (strpos($type, 'datetime') !== FALSE) {
            return 'datetime';
        } elseif (strpos($type, 'date') !== FALSE) {
            return 'date';
        } elseif (strpos($type, 'text') !== FALSE) {
            return 'textarea';
        } elseif (strpos($type, 'dropdown') !== FALSE) {
            return 'dropdown';
        } elseif (strpos($type, 'select') !== FALSE) {
            return 'select';
        } elseif (strpos($type, 'table') !== FALSE) {
            return 'table';
        } else {
            return 'text';
        }
    }

    protected function input_wrapper($field_type, $label, $input, $required)
    {
        $output = '<label for="' . $this->table . '[' . $field_type . ']">' . $label . ':' . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>' . $input . '';
        if ($this->validator && $this->validator->hasError($field_type)) {
            $output .= '<div class="error text-danger">' . $this->validator->getError($field_type) . '</div>';
        }
        return $output;
    }

    protected function select_wrapper($field_type, $label, $input, $required, $input_class = 'col-12')
    {
        $output = '<label class="p-0 ' . $input_class . '" for="' . $this->table . '[' . $field_type . ']">' . $label . ':' . ($required ? ' <span class="text-danger">*</span>' : '') . $input . '</label>';
        if ($this->validator && $this->validator->hasError($field_type)) {
            $output .= '<div class="error text-danger">' . $this->validator->getError($field_type) . '</div>';
        }
        return $output;
    }

    protected function checkbox_wrapper($field_type, $label, $input, $required)
    {
        $output = '<label for="' . $field_type . '">' . $label . ':' . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>
                <div class="form-check">' . $input . '</div>';
        if ($this->validator && $this->validator->hasError($field_type)) {
            $output .= '<div class="error text-danger">' . $this->validator->getError($field_type) . '</div>';
        }
        return $output;
    }

    protected function field_text($field_type, $label, $field_params)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $inputclass = ($field_params['inputclass'] ?? 'col-12');
        $input = '<input type="text" ' . $required . ' class="form-control '.$inputclass.'" id="' . $field_type . '" name="' . $this->table . '[' . $field_type . ']"  placeholder="" value="' . set_value($this->table . '[' . $field_type . ']', (isset($this->current_values[$field_type]) ? $this->current_values[$field_type] : '')) . '" autocomplete="off">';
        return $this->input_wrapper($field_type, $label, $input, $required);
    }

    protected function field_password($field_type, $label, $field_params, $field_values, $class)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $input = '<input type="password" ' . $required . ' class="form-control" id="' . $field_type . '" name="' . $this->table . '[' . $field_type . ']"   value="" >';
        $password = $this->input_wrapper($field_type, $label, $input, $required);
        $password_confirm = '';
        if (isset($field_params['confirm']) && $field_params['confirm']) {
            $input_confirm = '<input type="password" ' . $required . ' class="form-control" id="' . $field_type . '_confirm" name="' . $this->table . '[' . $field_type . '_confirm]"   value="" >';
            $password_confirm = '</div></div><div class="' . $class . '"><div class="form-group">' . $this->input_wrapper($field_type . '_confirm', $label . ' confirm', $input_confirm, $required);
        }
        return $password . $password_confirm;
    }

    protected function field_number($field_type, $label, $field_params)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $attr = '';
        
        if (isset($field_params['attr'])) {
            foreach ($field_params['attr'] as $key => $value) {
                $attr .= ' ' . $key . '="' . $value . '" ';
            }
        }
        $input = '<input type="number" ' . $required . ' ' . $attr . ' class="form-control" id="' . $field_type . '" name="' . $this->table . '[' . $field_type . ']"  placeholder="" value="' . set_value($this->table . '[' . $field_type . ']', (isset($this->current_values[$field_type]) ? $this->current_values[$field_type] : '')) . '" autocomplete="off">';
        return $this->input_wrapper($field_type, $label, $input, $required);
    }

    protected function field_currency($field_type, $label, $field_params)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $attr = '';
        
        if (isset($field_params['attr'])) {
            foreach ($field_params['attr'] as $key => $value) {
                $attr .= ' ' . $key . '="' . $value . '" ';
            }
        }
        $input = '<input type="currency" ' . $required . ' ' . $attr . ' class="form-control" id="' . $field_type . '" name="' . $this->table . '[' . $field_type . ']"  placeholder="" value="' . set_value($this->table . '[' . $field_type . ']', (isset($this->current_values[$field_type]) ? $this->current_values[$field_type] : '')) . '" autocomplete="off">';
        return $this->input_wrapper($field_type, $label, $input, $required);
    }

    protected function field_select($field_type, $label, $field_params, $values)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $input = '<select ' . $required . ' class="form-control" id="' . $field_type . '" name="' . $this->table . '[' . $field_type . ']"><option></option>';
        foreach ($values as $value) {
            $input .= '<option value="' . $value . '" ' . set_select($field_type, $value, (isset($this->current_values[$field_type]) && $this->current_values[$field_type] == $value ? TRUE : FALSE)) . '>' . ucfirst($value) . '</option>';
        }
        $input .= '</select><script>$(document).ready(function() {
    $("#' . $field_type . '").select2({width:"100%"});
});</script>';
        return $this->select_wrapper($field_type, $label, $input, $required);
    }

    protected function field_dropdown($field_type, $label, $field_params, $values)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        // randomize_id for select2
        $rand_number = mt_rand(1545645, 15456546);
        $rid = $field_type . '_' . $rand_number;
        $input_class = $field_params['inputclass'] ?? 'col-12';
        $input = '<select  class="form-control m-t-15" ' . $required . ' id="' . $rid . '" name="' . $this->table . '[' . $field_type . ']"><option value="-1"></option>';
        $pk = $field_params['relation']['primary_key'] ?? 'id';
        $display = $field_params['relation']['display'] ?? 'all';
        foreach ($values as $value) {
            if (is_array($display)) {
                $display_val = '';
                foreach ($display as $disp) {
                    $display_val .= $value[$disp] . ' ';
                }
                $display_val = trim($display_val);
            } else {
                if ($display == 'all') {
                    $display_val = implode(', ', $value);
                } else {
                    $display_val = $value[$display];
                }
            }
            $input .= '<option value="' . $value[$pk] . '" ' . set_select($field_type, $value[$pk], (isset($this->current_values[$field_type]) && $this->current_values[$field_type] == $value[$pk] ? TRUE : FALSE)) . '>' . $display_val . '</option>';
        }
        $input .= '</select><script>$(document).ready(function() {
    $("#' . $rid . '").select2({width:"resolve"});
});</script>';
        return $this->select_wrapper($field_type, $label, $input, $required, $input_class);
    }

    protected function field_multiselect($field_type, $label, $field_params, $values)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        // randomize_id for select2
        $rand_number = mt_rand(1545645, 15456546);
        $rid = $field_type . '_' . $rand_number;
        $input = '<input type="hidden" name="' . $this->table . '[' . $field_type . ']" value=""><select  class="form-control" ' . $required . ' id="' . $rid . '" name="' . $field_type . '[]" multiple="multiple">
                 <option></option>';
        
        $pk = $field_params['relation']['primary_key'];
        $display = $field_params['relation']['display'];
        // Values can be set from $_POST (if this is a form submission)
        if ($this->request->getPost($field_type)) {
            $val_arr = $this->request->getPost($field_type);
        } // Values can be set from save_table option
elseif ($field_params['relation']['save_table'] && $this->id) {
            $relItems = $this->model->getRelationItems($field_params['relation']['save_table'], [
                $field_params['relation']['parent_field'] => $this->id
            ]);
            $val_arr = [];
            if ($relItems) {
                foreach ($relItems as $relItem) {
                    $val_arr[] = $relItem->{$field_params['relation']['child_field']};
                }
            }
        } // Values can be set from current row (if this is an editing of the record and no $_POST yet)
elseif ($this->current_values && $this->current_values[$field_type] != '') {
            $val_arr = explode(',', $this->current_values[$field_type]);
        } // Values can be an emty array if none of the above is true
else {
            $val_arr = [];
        }
        
        foreach ($values as $value) {
            if (is_array($display)) {
                $display_val = '';
                foreach ($display as $disp) {
                    $display_val .= $value->{$disp} . ' ';
                }
                $display_val = trim($display_val);
            } else {
                $display_val = $value->{$display};
            }
            
            $input .= '<option value="' . $value->{$pk} . '" ' . set_select($field_type, $value->{$pk}, (in_array($value->{$pk}, $val_arr) ? TRUE : FALSE)) . '>' . $display_val . '</option>';
        }
        $input .= '</select><script>$(document).ready(function() {
    $("#' . $rid . '").select2({theme: "bootstrap4",width:"100%"});
});</script>';
        return $this->select_wrapper($field_type, $label, $input, $required);
    }

    protected function field_simple_dropdown($field_type, $label, $field_params, $values)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        // randomize_id for select2
        $rand_number = mt_rand(1545645, 15456546);
        $input_class = $field_params['inputclass'] ?? 'col-12';
        $rid = $field_type . '_' . $rand_number;
        $input = '<select  class="form-control m-t-15" ' . $required . ' id="' . $rid . '" name="' . $this->table . '[' . $field_type . ']"><option value="-1"></option>';
        $input .= '</select><script>$(document).ready(function() {
    $("#' . $rid . '").select2();
});</script>';
        return $this->select_wrapper($field_type, $label, $input, $required);
    }

    protected function field_checkboxes($field_type, $label, $field_params, $values)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        
        // $input = '<input name="' .$this->table .'['. $field_type . ']" type="checkbox" value="" checked style="display:none;">';
        $input = '<div class="row">';
        $pk = $field_params['relation']['primary_key'];
        $display = $field_params['relation']['display'];
        $inner_class = $field_params['relation']['inner_class'] ?? 'col-12';
        
        // Values can be set from $_POST (if this is a form submission)
        if ($this->request->getPost($field_type)) {
            $val_arr = $this->request->getPost($field_type);
        } elseif ($field_params['relation']['save_table'] && $this->id) {
            $relItems = $this->model->getRelationItems($field_params['relation']['save_table'], [
                $field_params['relation']['parent_field'] => $this->id
            ]);
            $val_arr = [];
            if ($relItems) {
                foreach ($relItems as $relItem) {
                    $val_arr[] = $relItem->{$field_params['relation']['child_field']};
                }
            }
        } // Values can be set from current row (if this is an editing of the record and no $_POST yet)
elseif ($this->current_values && $this->current_values[$field_type] != '') {
            $val_arr = explode(',', $this->current_values[$field_type]);
        } // Values can be an emty array if none of the above is true
else {
            $val_arr = [];
        }
        
        $i = 0;
        
        foreach ($values as $value) {
            if (is_array($display)) {
                $display_val = '';
                foreach ($display as $disp) {
                    $display_val .= $value->{$disp} . ' ';
                }
                $display_val = trim($display_val);
            } else {
                $display_val = $value->{$display};
            }
            
            $checkboxId = $field_type . '-' . $i;
            $input .= '<label class="form-check-label  ' . $inner_class . '" for="' . $checkboxId . '"> <input
                        value="' . $value->{$pk} . '"
                        type="checkbox" 
                        class="form-check-input" 
                        name="' . $field_type . '[]" ' . (in_array($value->{$pk}, $val_arr) ? ' checked ' : '') . 'id="' . $checkboxId . '">';
            $input .= $display_val . '</label>';
            
            $i ++;
        }
        $input .= '</div>';
        return $this->checkbox_wrapper($field_type, $label, $input, $required);
    }

    protected function field_email($field_type, $label, $field_params)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $inputclass = ($field_params['inputclass'] ?? 'col-12');
        $input = '<input type="email" ' . $required . ' class="form-control '.$inputclass.'" id="' . $field_type . '" name="' . $this->table . '[' . $field_type . ']"  placeholder="" value="' . set_value($this->table . '[' . $field_type . ']', (isset($this->current_values[$field_type]) ? $this->current_values[$field_type] : '')) . '" autocomplete="off">';
        return $this->input_wrapper($field_type, $label, $input, $required);
    }

    protected function field_hidden($field_type, $label, $field_params)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $input = '<input type="hidden" ' . $required . '  id="' . $field_type . '" name="' . $this->table . '[' . $field_type . ']"  placeholder="" value="' . (isset($this->current_values[$field_type]) ? $this->current_values[$field_type] : $field_params['value']) . '" >';
        return $input;
    }

    protected function field_datetime($field_type, $label, $field_params)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $inputclass = ($field_params['inputclass'] ?? 'col-12');
        $input = '<div class="input-group date '.$inputclass.'" id="' . $field_type . '" data-target-input="nearest">
        <input 
        type="date"  ' . $required . ' 
        class="form-control datetimepicker-input" 
        data-target="#' . $field_type . '"
        id="datetime-' . $field_type . '" name="' . $this->table . '[' . $field_type . ']" 
        placeholder="" value="' . set_value($this->table . '[' . $field_type . ']', (isset($this->current_values[$field_type]) ? $this->current_values[$field_type] : '')) . '" />
        <div class="input-group-append" data-target="#' . $field_type . '" data-toggle="datetimepicker">
                      </div>
    </div>';
        
        // $input = '<input type="datetime-local" '.$required.' class="form-control" id="'.$field_type.'" name="'.$field_type.'" placeholder="" value="'.set_value($this->table .'['. $field_type . ']', (isset($this->current_values[$field_type]) ? $this->current_values[$field_type] : '')).'" autocomplete="off">';
        return $this->input_wrapper($field_type, $label, $input, $required);
    }

    protected function field_date($field_type, $label, $field_params)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $inputclass = ($field_params['inputclass'] ?? 'col-12');
        $input = '<input 
        type="date"  ' . $required . ' 
        class="form-control datetimepicker-input '.$inputclass.'" 
        data-target="#' . $field_type . '"
        id="datetime-' . $field_type . '" name="' . $this->table . '[' . $field_type . ']" 
        placeholder="" value="' . set_value($this->table . '[' . $field_type . ']', (isset($this->current_values[$field_type]) ? $this->current_values[$field_type] : '')) . '" />';
        
        // $input = '<input type="datetime-local" '.$required.' class="form-control" id="'.$field_type.'" name="'.$field_type.'" placeholder="" value="'.set_value($this->table .'['. $field_type . ']', (isset($this->current_values[$field_type]) ? $this->current_values[$field_type] : '')).'" autocomplete="off">';
        return $this->input_wrapper($field_type, $label, $input, $required);
    }

    protected function field_textarea($field_type, $label, $field_params)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $input = '<textarea  id="' . $field_type . '"   name="' . $this->table . '[' . $field_type . ']"  class="form-control" rows="5" ' . $required . ' placeholder="">' . set_value($this->table . '[' . $field_type . ']', (isset($this->current_values[$field_type]) ? $this->current_values[$field_type] : '')) . '</textarea>';
        return $this->input_wrapper($field_type, $label, $input, $required);
    }

    protected function field_editor($field_type, $label, $field_params)
    {
        $rand_number = mt_rand(1545645, 15456546);
        $rid = $field_type . '_' . $rand_number;
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $input = '<textarea  id="' . $rid . '"   name="' . $this->table . '[' . $field_type . ']"  class="form-control" rows="5" ' . $required . ' placeholder="">' . set_value($this->table . '[' . $field_type . ']', (isset($this->current_values[$field_type]) ? $this->current_values[$field_type] : '')) . '</textarea>';
        $input .= '
    <script>$(document).ready(function() {
      $("#' . $rid . '").summernote({
        height: 150,
        toolbar: [
          [\'style\', [\'style\',\'bold\', \'italic\', \'underline\', \'clear\']],
          [\'font\', [\'strikethrough\']],
          [\'fontsize\', [\'fontsize\',\'fontname\']],
          [\'color\', [\'color\']],
          [\'para\', [\'ul\', \'ol\', \'paragraph\']],
          [\'insert\', [ \'video\']],
          [\'misc\', [ \'codeview\']],
        ],
        fontSizes: [ "14", "16","18", "20", "22"],
      });
    });</script>
    ';
        return $this->input_wrapper($field_type, $label, $input, $required);
    }

    protected function field_file($field_type, $label, $field_params)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $input = '<div class="custom-file">
                      <input 
                      type="file"
                      name="' . $this->table . '[' . $field_type . ']" 
                      class="custom-file-input" 
                      id="' . $field_type . '">
                      <label class="custom-file-label" for="customFile">Choose file</label>
                    </div>';
        if (isset($field_params['wrapper_start']) && isset($field_params['wrapper_end'])) {
            $fileName = $this->files[$field_type] ?? null;
            $htmlFileName = '';
            $deleteButton = '';
            
            if ($field_params['show_file_names'] ?? false)
                $htmlFileName .= '<div class="file-name-wrapper text-center">' . $fileName . '</div>';
            
            if ($field_params['delete_callback'] ?? false) {
                $deleteUrl = $this->getBase() . '/' . $this->getTable() . '/' . $field_params['delete_callback'] . '/' . $this->id;
                $deleteButton .= '<a 
                        onclick="return confirm(\'Are you sure you want to delete this file?\')" 
                        class="' . ($field_params['delete_button_class'] ?? null) . '" 
                        href="' . $deleteUrl . '">Delete</a>';
            }
            
            if ($fileName) {
                $src = ltrim($field_params['path'], '.') . '/' . $fileName;
                $input .= $field_params['wrapper_start'] . '<a href="' . $src . '" target="_blank">';
                
                if (($field_params['is_image'] ?? false) && $field_params['is_image'] === TRUE) {
                    $input .= '<img class="img-fluid" src="' . $src . '">';
                } elseif (isset($field_params['placeholder'])) {
                    $input .= '<img class="img-fluid" src="' . $field_params['placeholder'] . '">';
                }
                
                $input .= $htmlFileName . '</a>' . $deleteButton . $field_params['wrapper_end'];
            }
        }
        
        return $this->input_wrapper($field_type, $label, $input, $required);
    }

    protected function field_files($field_type, $label, $field_params)
    {
        $required = (isset($field_params['required']) && $field_params['required'] ? ' required ' : '');
        $relationOptions = $field_params['files_relation'];
        $input = '<div class="custom-file">
                      <input 
                      multiple
                      type="file"
                      name="' . $field_type . '[]" 
                      class="custom-file-input" 
                      id="' . $field_type . '">
                      <label class="custom-file-label" for="customFile">Choose file</label>
                    </div>';
        if (isset($field_params['wrapper_start']) && isset($field_params['wrapper_end'])) {
            
            if ($files = $this->files[$field_type] ?? null) {
                $input .= $field_params['wrapper_start'];
                
                foreach ($files as $file) {
                    $fileType = $file->{$relationOptions['file_type_field']};
                    $fileName = $file->{$relationOptions['file_name_field']};
                    $htmlFileName = '';
                    if ($field_params['show_file_names'] ?? false)
                        $htmlFileName .= '<div class="file-name-wrapper text-center">' . $fileName . '</div>';
                    if ($field_params['delete_callback'] ?? false) {
                        $deleteUrl = $this->getBase() . '/' . $this->getTable() . '/' . $field_params['delete_callback'] . '/' . $this->id . '/' . $file->{$relationOptions['primary_key']};
                        $htmlFileName .= '<a 
                        onclick="return confirm(\'Are you sure you want to delete this file?\')" 
                        class="' . ($field_params['delete_button_class'] ?? null) . '" 
                        href="' . $deleteUrl . '">Delete</a>';
                    }
                    
                    $input .= $field_params['wrapper_item_start'];
                    $src = ltrim($field_params['path'], '.') . '/' . $this->id . '/' . $fileName;
                    if (strpos($fileType, 'image') !== false) {
                        $input .= '<a href="' . $src . '" target="_blank"><img class="img-fluid" src="' . $src . '">' . $htmlFileName . '</a>';
                    } elseif (strpos($fileType, 'video') !== false) {
                        $input .= '<video class="img-fluid" src="' . $src . '" controls></video><a href="' . $src . '" target="_blank">' . $htmlFileName . '</a>';
                    } else {
                        if (isset($field_params['placeholder'])) {
                            $placeholder = '<img class="img-fluid" src="' . $field_params['placeholder'] . '">';
                        } else {
                            $placeholder = '<i class="fas fa-file"></i>';
                        }
                        $input .= '<a href="' . $src . '" target="_blank" class="d-block text-center">' . $placeholder . ' ' . $htmlFileName . '</a>';
                    }
                    
                    $input .= $field_params['wrapper_item_end'];
                }
                $input .= $field_params['wrapper_end'];
            }
        }
        
        return $this->input_wrapper($field_type, $label, $input, $required);
    }

    // ///////////////////////
    // ///////////////////////
    // /////HELPERS/////////
    // ///////////////////////
    // ///////////////////////
    protected function items_table()
    {
        $fields = $this->fields;
        $columns = $this->fields;
        $items = $this->data;
        $primary_key = $this->get_primary_key_field_name();
        
        $table = '<div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">' . $this->table_title . '</h3>
                <div class="card-tools">
                  <a class="btn btn-primary btn-sm" href="' . $this->base . '/' . $this->table . '/add">' . $this->form_title_add . '</a>
                </div>
              </div>
              <!-- /.card-header -->
              <div class="card-body table-responsive">
                <table class="table table-hover table-sm text-nowrap" id="' . $this->table . '">';
        
        if ($columns) {
            
            foreach ($columns as $column) {
                if ((isset($column['type']) && $column['type'] === 'unset') or (isset($column['table']) && $column['table'] === 'none')) {
                    continue;
                }
                if (is_array($column)) {
                    $label = $this->get_label($column);
                    $th_class = ucfirst(str_replace('_', ' ', $label));
                } else {
                    $label = ucfirst(str_replace('_', ' ', $column));
                    $th_class = $column;
                }
                $table .= '<th class="th-' . $th_class . '">' . $label . '</th>';
            }
        }
        
        $table .= '<th class="th-action" width="10%">Actions</th>';
        $table .= '</tr></thead><tbody><tr>';
        
        // Result items
        
        foreach ($items as $item) {
            
            $table .= '<tr class="row_item" >';
            $fields = $this->fields;
            $mainItem = $item;
            if ($columns) {
                foreach ($columns as $name => $column) {
                    if ((isset($column['type']) && $column['type'] === 'unset') or (isset($column['table']) && $column['table'] === 'none')) {
                        continue;
                    }
                    
                    if (is_array($column)) {
                        if (isset($column['relation'])) {
                            
                            if (is_string($column['relation'])) {
                                $rel['table'] = $column['relation'];
                                $rel['child_field'] = $column['relation'] . 'id';
                                $rel['primary_field'] = $name;
                                $model = model('App\Models\\' . ucfirst($rel['table']));
                                $res = $model->find((int) $item[$name]);
                                $item[$name] = implode(',', $res);
                            } elseif (isset($column['relation']) && is_array($column['relation'])) {
                                $rel = $column['relation'];
                                $display_val = '';
                                if (! isset($rel['table'])) {
                                    $item = null;
                                }
                                if (! isset($rel['child_field'])) {
                                    $rel['child_field'] = $rel['table'] . '.id';
                                }
                                if (! isset($rel['primary_field'])) {
                                    $rel['primary_field'] = $name;
                                }
                                if (! isset($rel['display'])) {
                                    $rel['display'] = 'all';
                                }
                                if (! isset($rel['limit'])) {
                                    $rel['limit'] = 1;
                                }
                                if (! isset($rel['offset'])) {
                                    $rel['offset'] = 0;
                                }
                                if (! isset($rel['order'])) {
                                    $rel['order'] = 'id ASC';
                                }
                                $where = [
                                    $rel['child_field'] => $mainItem[$rel['primary_field']]
                                ];
                                if (isset($rel['where'])) {
                                    $where[] = $rel['where'];
                                }
                                $model = model('App\Models\\' . ucfirst($rel['table']))->where($where)
                                    ->limit($rel['limit'], $rel['offset'])
                                    ->orderBy($rel['order']);
                                if ($rel['limit'] == 1) {
                                    $res = $model->first();
                                } else {
                                    $res = $model->findAll();
                                }
                                // echo model('App\Models\\' . ucfirst($rel['table']))->getLastQuery();
                                if (is_array($rel['display'])) {
                                    foreach ($rel['display'] as $display) {
                                        if (! key_exists($display, $res)) {
                                            continue;
                                        }
                                        $display_val .= $res[$display] . ',';
                                    }
                                } elseif (is_string($rel['display'])) {
                                    if ($rel['display'] == 'all') {
                                        $display_val = implode(',', $res);
                                    } else {
                                        $display_val = $res[$rel['display']] ?? null;
                                    }
                                }
                                $item[$name] = $display_val;
                            }
                        }
                        if (isset($column['callback'])) {
                            if (is_string($column['callback'])) {
                                $display_val = $this->{$column['callback']}($item);
                            } elseif (is_callable($column['callback'])) {
                                $display_val = $column['callback']($item);
                            }
                        } else {
                            $display_val = ($item[$name] ?? null);
                        }
                    } else
                        $display_val = $item->{$column};
                    
                    $table .= '<td ' . (key_exists('class', $column) ? 'class="' . $column['class'] . '"' : '') . '>' . $display_val . '</td>';
                }
            }
            $table .= '<td class="text-center"><a href="' . $this->base . '/' . $this->table . '/edit/' . $item[$primary_key] . '" class="btn btn-success btn-sm">Edit</a></td>';
            $table .= '</tr>';
        }
        $table .= '</tbody></table></div>';
        $table .= '<div class="card-footer clearfix">';
        $table .= '</div></div>';
        
        return $table;
    }

    public function multipart($enum = true)
    {
        $this->multipart = $enum;
        return $this;
    }

    public function setAction($action = 'add')
    {
        $this->action = $action;
        return $this;
    }

    public function schema()
    {
        if (cache($this->table . '-schema') !== null) {
            return cache($this->table . '-schema');
        }
        $query = "SHOW COLUMNS FROM $this->table";
        $result = $this->db->query($query)->getResult();
        cache()->save($this->table . '-schema', $result);
        return $result;
    }

    public function get_primary_key_field_name()
    {
        foreach ($this->fields as $f) {
            if (isset($f['key']) && $f['key'] == 'PRI') {
                return $f['field'];
            }
            return 'id';
        }
        return 'id';
    }

    // Set flashdata session
    public function flash($key, $value)
    {
        $session = session();
        $session->setFlashdata($key, $value);
        return true;
    }

    public function getTableTitle()
    {
        return $this->table_title;
    }

    public function getAddTitle()
    {
        return $this->form_title_add;
    }

    public function getEditTitle()
    {
        return $this->form_title_update;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getBase()
    {
        return $this->base;
    }

    protected function formHasFileFields()
    {
        foreach ($this->fields as $field) {
            $type = $field['type'] ?? false;
            if (! $type)
                continue;
            
            if ($type == 'file' || $type == 'files')
                return true;
        }
        
        return false;
    }

    protected function fileHandler($fileFieldName, $fieldOptions)
    {
        $file = $this->request->getFile($fileFieldName);
        if ($file->isValid() && ! $file->hasMoved()) {
            $file->move($fieldOptions['path']);
            return $file->getName();
        }
        
        return false;
    }

    protected function filesHandler($fileFieldName, $fileFieldOptions)
    {
        $newFilesData = [];
        $fileRelationOptions = $fileFieldOptions['files_relation'];
        if ($files = $this->request->getFiles()) {
            foreach ($files[$fileFieldName] as $file) {
                if ($file->isValid() && ! $file->hasMoved()) {
                    $file->move(rtrim($fileFieldOptions['path'], '/') . '/' . $this->id);
                    $newFilesData[] = [
                        $fileRelationOptions['parent_field'] => $this->id,
                        $fileRelationOptions['file_name_field'] => $file->getName(),
                        $fileRelationOptions['file_type_field'] => $file->getClientMimeType()
                    ];
                } else
                    return false;
            }
        }
        
        if ($newFilesData)
            return $this->model->batchInsert($fileRelationOptions['files_table'], $newFilesData);
        
        return false;
    }

    /**
     * Get all the file fields from current_values
     * Do not use to get posted values $_FILES
     */
    public function getFiles($field = false)
    {
        if (! $field)
            return $this->files;
        
        if (isset($this->files[$field]))
            return $this->files[$field];
        
        return false;
    }

    public function deleteItem($table, $where)
    {
        $item = $this->model->getItem($table, $where);
        
        if ($item)
            $this->model->deleteItems($table, $where);
        
        return $item;
    }

    public function updateItem($table, $where, $data)
    {
        $affected = $this->model->updateItem($table, $where, $data);
        return $affected;
    }

    public function getItem($table, $where)
    {
        return $this->model->getItem($table, $where);
    }

    public function getFields($field = false)
    {
        if (! $field)
            return $this->fields;
        
        if ($field = $this->fields[$field] ?? false)
            return $field;
        
        return false;
    }

    protected function seq()
    {
        return $this->seq ++;
    }
}
