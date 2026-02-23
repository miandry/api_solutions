<?php


namespace Drupal\api_solutions;

/**
 * Class CRUDBaseService.
 */
class CRUDBaseService implements CRUDInterface
{
    /**if field id exist then update else create new one **/
    public function save($entity_type, $bundle, $fields, $reference_object = null)
    {
        $id_label = \Drupal::entityTypeManager()->getDefinition($entity_type)->getKey('id');
        $key_label = \Drupal::entityTypeManager()->getDefinition($entity_type)->getKey('label');


        if ($reference_object == null) {
            $reference_object = $this;
        }

        if ($entity_type == 'comment') {
            $entity_new = $this->create_init_comment($entity_type, $bundle, $fields);
        } else {
            $entity_new = $this->create_init_entity($entity_type, $bundle);
        }

        if (!is_object($entity_new)) {
            return false;
        }
        $is_new = true;
        if (isset($fields[$id_label]) && is_numeric($fields[$id_label])) {
            $entity_new = \Drupal::entityTypeManager()->getStorage($entity_type)->load($fields[$id_label]);
            $is_new = false;
        }
        if (!empty($fields)) {
            $keys = array_keys($fields);
            if (!in_array($id_label, $keys)) {
                $fields[$id_label] = null;
            }
            if ($is_new) {
                //new entity
                if ($key_label != "" && !in_array($key_label, $keys)) {
                    $fields[$key_label] = $key_label . ' - EMPTY';
                }
            } else {
                //update entity
                if ($key_label != "" && !in_array($key_label, $keys)) {
                    $fields[$key_label] = ($entity_new) ? $entity_new->label() : $key_label . ' - EMPTY';
                }
            }


            $changed_fields = [];
            foreach ($fields as $key => $field) {
                if ($entity_new && $entity_new->hasField($key)) {
                    $status = true;
                    $field_type = $entity_new->get($key)->getFieldDefinition()->getType();
                    $setting_field = $entity_new->get($key)->getFieldDefinition()->getSettings();

                    // Track changes for existing entities
                    $old_value = !$is_new ? $entity_new->get($key)->getValue() : null;

                    ///hook by type
                    if ($setting_field && isset($setting_field['target_type'])) {
                        $func = $field_type . '_' . $setting_field['target_type'];
                        if ($reference_object && method_exists($reference_object, $func)) {
                            $entity_new = $reference_object->{$func}($entity_new, $key, $field);
                            $status = false;
                        } else {
                            //hook by type
                            if ($reference_object && $field_type && method_exists($reference_object, $field_type)) {
                                $entity_new = $reference_object->{$field_type}($entity_new, $key, $field);
                                $status = false;
                            }
                        }
                    } else {
                        if ($reference_object && $field_type && method_exists($reference_object, $field_type)) {
                            $entity_new = $reference_object->{$field_type}($entity_new, $key, $field);
                            $status = false;
                        }
                    }



                    //default
                    if ($status) {
                        $this->item_default($entity_new, $key, $field);
                    }

                    // Check if value changed
                    if (!$is_new) {
                        $new_value = $entity_new->get($key)->getValue();
                        if (json_encode($old_value) !== json_encode($new_value)) {
                            $changed_fields[] = $key;
                        }
                    }

                }

            }


        }

        // Enable revisions if supported
        if ($entity_new instanceof \Drupal\Core\Entity\RevisionableInterface) {
            $entity_new->setNewRevision(TRUE);

            if ($entity_new instanceof \Drupal\Core\Entity\RevisionLogInterface) {
                $log_msg = 'API Update: ' . ($is_new ? 'Created' : 'Updated') . ' via api_solutions.';
                if (!$is_new && !empty($changed_fields)) {
                    $log_msg .= ' Changed fields: ' . implode(', ', $changed_fields);
                }
                $entity_new->setRevisionLogMessage($log_msg);
                $entity_new->setRevisionCreationTime(\Drupal::time()->getRequestTime());
                $entity_new->setRevisionUserId(\Drupal::currentUser()->id());
            }
        }

        $status = $entity_new->save();

        if ($status) {
            return $entity_new;
        } else {
            return null;
        }
    }

    public function create_init_comment($entity_type, $bundle, $fields)
    {

        if (
            isset($fields['entity_type']) && isset($fields['entity_id'])
            && isset($fields['field_name']) && isset($fields['subject']) && isset($fields['uid'])
        ) {
            $fields['uid'] = \Drupal::currentUser()->id();
            if (!isset($fields['pid'])) {
                $fields['pid'] = NULL;
            }
            return \Drupal::entityTypeManager()->getStorage('comment')->create($fields);
        }
        \Drupal::logger("api_solutions")->error("Comment creation failed");
        \Drupal::messenger()->addMessage('Entity Comments require fields : entity_type, entity_id, field_name,subject,uid,comment_body', 'error');
        return false;


    }
    // ********* SAVE FUNCTION ENTITY ******** //

    protected function create_init_entity($entity_type, $bundle, $id = null)
    {

        $entity_def = \Drupal::entityTypeManager()->getDefinition($entity_type);
        $uuid = \Drupal::service('uuid');
        $uuid_key = $uuid->generate();
        $key_label = \Drupal::entityTypeManager()->getDefinition($entity_type)->getKey('label');
        $array = array(
            $key_label => "empty",
            $entity_def->get('entity_keys')['bundle'] => $bundle,
            "uuid" => $uuid_key
        );
        return \Drupal::entityTypeManager()->getStorage($entity_type)->create($array);
    }

    public function item_default($entity_parent, $field_name, $field_value)
    {
        $entity_parent->set($field_name, $field_value);
        return $entity_parent;
    }

}
