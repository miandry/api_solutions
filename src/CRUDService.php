<?php

namespace Drupal\api_solutions;

use Drupal\Core\File\FileSystemInterface;

/**
 * Class CRUDService.
 */
class CRUDService extends CRUDBaseService
{

    /**
     * Constructs a new CRUDService object.
     */
    public function __construct()
    {

    }
    public function paragraph($type, $fields, $reference_object = null)
    {
        return $this->save('paragraph', $type, $fields, $reference_object);
    }

    //********** Reference format *********//
    //default
    public function node($type, $fields, $reference_object = null)
    {
        return $this->save('node', $type, $fields, $reference_object);
    }

    //string
    public function string($entity_parent, $field_name, $field_value)
    {
        return $this->item_default($entity_parent, $field_name, $field_value);
    }

    //float
    public function float($entity_parent, $field_name, $field_value)
    {
        return $this->item_default($entity_parent, $field_name, $field_value);
    }

    // ** image type field insertion api ***//
    public function image($entity_parent, $field_name, $field_value)
    {
        $field_images = [];
        if (is_string($field_value) && !is_numeric($field_value)) {
            $field_images[] = $this->saveImgFile($entity_parent, $field_name, $field_value);
        } else {
            if (is_array($field_value)) {
                foreach ($field_value as $image) {
                    if (is_string($image) && !is_numeric($image)) {
                        $field_images[] = $this->saveImgFile($entity_parent, $field_name, $image);
                    }
                    if (is_array($image)) {
                        //if type exist
                        if (isset($image['uri'])) {
                            $image_url = file_create_url($image['uri']);
                        } else {
                            $image_url = $image['url'];
                        }
                        $field_images[] = $this->saveImgFile($entity_parent, $field_name, $image_url, $image);
                    }
                }
            }
        }
        if (!empty($field_images)) {
            $entity_parent->set($field_name, $field_images);
        }
        return $entity_parent;
    }
    public function file_file($entity_parent, $field_name, $field_value)
    {
        $field_result = null;
        $field_array = explode('/', $field_value);
        $filename = end($field_array);
        $data = file_get_contents($field_value);
        if ($data) {
            $setting = $entity_parent->get($field_name)->getSettings();
            $file_directory = ($setting['file_directory']);
            $path_root = 'public://' . $file_directory . '/';
            // Replace the token.
            $token_service = \Drupal::token();
            $path_root = $token_service->replace($path_root);
            $file_system = \Drupal::service('file_system');
            if ($file_system->prepareDirectory($path_root, FileSystemInterface::CREATE_DIRECTORY)) {
                $file = file_save_data($data, $path_root . "/" . $filename, FileSystemInterface::EXISTS_RENAME);
                if ($file) {
                    $field_result = array(
                        'target_id' => $file->id()
                    );
                }
            } else {
                $message = "Directory not found  " . $path_root;
                \Drupal::logger("api_solutions")->error($message);
            }
        } else {
            $message = "File not found ";
            \Drupal::logger("api_solutions")->error($message);
        }
        if (!empty($field_result)) {
            $entity_parent->set($field_name, $field_result);
        }
        return $entity_parent;

    }

    public function saveImgFile($entity_parent, $field_image, $field_value, $array = [])
    {
        $field_image_result = null;
        $data = null;
        $filename = null;

        if (strpos($field_value, 'data:image/') === 0) {
            // Support Base64
            $parts = explode(',', $field_value);
            if (count($parts) > 1) {
                $header = $parts[0];
                $data = base64_decode($parts[1]);
                preg_match('/data:image\/(.*?);/', $header, $matches);
                $ext = isset($matches[1]) ? $matches[1] : 'png';
                $filename = 'base64_' . time() . '.' . $ext;
            }
        } else {
            $field_array = explode('/', $field_value);
            $filename = end($field_array);
            $data = @file_get_contents($field_value);
        }

        if ($data) {
            $setting = $entity_parent->get($field_image)->getSettings();
            $file_directory = ($setting['file_directory']);
            $path_root = 'public://' . $file_directory . '/';
            // Replace the token.
            $token_service = \Drupal::token();
            $path_root = $token_service->replace($path_root);
            $file_system = \Drupal::service('file_system');
            if ($file_system->prepareDirectory($path_root, FileSystemInterface::CREATE_DIRECTORY)) {
                $file = file_save_data($data, $path_root . "/" . $filename, FileSystemInterface::EXISTS_RENAME);
                if ($file) {
                    $field_image_result = array(
                        'target_id' => $file->id(),
                        'alt' => isset($array["alt"]) ? $array["alt"] : "",
                        'title' => isset($array["title"]) ? $array["title"] : "",
                    );
                }
            } else {
                $message = "Directory not found  " . $path_root;
                \Drupal::logger("api_solutions")->error($message);
            }
        } else {
            $message = "Image not found ";
            \Drupal::logger("api_solutions")->error($message);
        }
        return $field_image_result;
    }

    // paragraph
    public function entity_reference_revisions($entity_parent, $field_name, $field_value)
    {

        if (is_object($field_value)) {
            $entity_parent->set($field_name, $field_value);
        }
        if (is_numeric($field_value)) {
            $paragraph[] = [
                'target_id' => $field_value,
                'target_revision_id' => $field_value,
            ];
            $entity_parent->set($field_name, $paragraph);
        }
        if (is_array($field_value) && !empty($field_value)) {
            $setting_field = $entity_parent->get($field_name)->getFieldDefinition()->getSettings();
            $bundle = end($setting_field['handler_settings']['target_bundles']);
            foreach ($field_value as $item) {
                if (is_array($item)) {
                    $field_items[] = $this->save('paragraph', $bundle, $item);
                } else {
                    if (is_numeric($item)) {
                        $field_items[] = array(
                            "target_id" => $item,
                            'target_revision_id' => $item,
                        );
                    }
                    if (is_object($item) && $item->id()) {
                        $field_items[] = array(
                            'target_id' => $item->id(),
                            'target_revision_id' => $item->getRevisionId(),
                        );
                    }
                }
            }
            $entity_parent->set($field_name, $field_items);
        }
        return $entity_parent;
    }

    //entity_reference media
    public function entity_reference_media($entity_parent, $field_name, $field_value)
    {
        $setting_field = $entity_parent->get($field_name)->getFieldDefinition()->getSettings();
        $bundle = end($setting_field['handler_settings']['target_bundles']);
        $key_label = \Drupal::entityTypeManager()->getDefinition('media')->getKey('label');
        if (is_string($field_value) && !is_numeric($field_value)) {
            switch ($bundle) {
                // Main module help for the content_export module.
                case 'image':
                    $array = explode('/', $field_value);
                    $filename = end($array);
                    $media = $this->save(
                        'media',
                        $bundle,
                        [
                            'field_media_image' => $field_value,
                            $key_label => $filename,
                        ]
                    );
                    $entity_parent->{$field_name}->entity = $media;
                    break;
                case 'document':
                    $array = explode('/', $field_value);
                    $filename = end($array);
                    $media = $this->save(
                        'media',
                        $bundle,
                        [
                            'field_media_document' => $field_value,
                            $key_label => $filename,
                        ]
                    );
                    $entity_parent->{$field_name}->entity = $media;
                    break;
            }

        }
        if (is_numeric($field_value)) {
            $entity_parent->{$field_name}->target_id = $field_value;
        }
        if (is_object($field_value)) {
            $entity_parent->{$field_name}->entity = $field_value;
        }
        if (is_array($field_value) && !empty($field_value)) {
            foreach ($field_value as $item) {
                if (is_numeric($item)) {
                    $field_items[] = array("target_id" => $item);
                }
                if (is_object($item) && $item->id()) {
                    $field_items[] = array(
                        'target_id' => $item->id(),
                    );
                }
                switch ($bundle) {
                    // Main module help for the content_export module.
                    case 'image':
                        if (is_array($item)) {
                            if (isset($item['uri'])) {
                                $item = file_create_url($item['uri']);
                            } else {
                                $item = isset($item['url']) ? $item['url'] : null;
                            }
                        }

                        if (is_string($item) && !is_numeric($item)) {
                            $filename_parts = explode('/', $item);
                            $filename = end($filename_parts);
                            $media = $this->save(
                                'media',
                                $bundle,
                                [
                                    'field_media_image' => $item,
                                    $key_label => $filename,
                                ]
                            );
                            $field_items[] = array(
                                'target_id' => $media->id(),
                            );
                        }

                        break;
                }

            }
            $entity_parent->set($field_name, $field_items);
        }
        return $entity_parent;
    }

    protected function is_exits($entity_type, $bundle, $value)
    {
        $status = true;
        $key_label = \Drupal::entityTypeManager()->getDefinition($entity_type)->getKey('label');
        $bundle_label = \Drupal::entityTypeManager()->getDefinition($entity_type)->getKey('bundle');
        $query = \Drupal::entityQuery($entity_type);
        $query->condition($key_label, $value, 'CONTAINS');
        $query->condition($bundle_label, $bundle);
        $query->range(0, 1);
        if ($status) {
            return $query->execute();
        } else {
            return [];
        }
    }

    // entity_reference term
    public function entity_reference_taxonomy_term($entity_parent, $field_name, $field_value)
    {
        return $this->entity_reference($entity_parent, $field_name, $field_value);
    }
    public function entity_reference_comment_type($entity_parent, $field_name, $field_value)
    {
        return $this->item_default($entity_parent, $field_name, $field_value);
    }
    public function entity_reference($entity_parent, $field_name, $field_value)
    {
        $setting_field = $entity_parent->get($field_name)->getFieldDefinition()->getSettings();
        $entity_type = ($setting_field['target_type']);
        $bundle = ($setting_field['handler_settings'] && $setting_field['handler_settings']['target_bundles']) ? end($setting_field['handler_settings']['target_bundles']) : $entity_type;
        $fields_node_config = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);
        $field_list = array_keys($fields_node_config);
        $key_label = \Drupal::entityTypeManager()->getDefinition($entity_type)->getKey('label');

        if (is_string($field_value) && !is_numeric($field_value)) {

            $term_exist = $this->is_exits($entity_type, $bundle, $field_value);
            if (sizeof($term_exist) == 0) {
                $term = $this->save(
                    $entity_type,
                    $bundle,
                    [
                        $key_label => $field_value,
                    ]
                );
                $entity_parent->{$field_name}->entity = $term;
            } else {
                $entity_parent->{$field_name}->target_id = end($term_exist);
            }
        }
        if (is_object($field_value)) {
            $entity_parent->{$field_name}->entity = $field_value;
        }
        if (is_numeric($field_value)) {
            $entity_parent->{$field_name}->target_id = $field_value;
        }
        if (is_array($field_value) && !empty($field_value)) {
            $field_items = [];
            foreach ($field_value as $item) {
                // [Array,Array]
                if (is_array($item)) {
                    $field_terms = [];
                    foreach ($item as $key => $field_item) {
                        if (in_array($key, $field_list)) {
                            $field_terms[$key] = $field_item;
                        }
                    }
                    if (!empty($field_terms)) {
                        $term_new = $this->save($entity_type, $bundle, $field_terms);
                        if (is_object($term_new) && $term_new->id()) {
                            $field_items[] = array(
                                'target_id' => $term_new->id(),
                            );
                        }
                    }
                } else {
                    // if [12,12,21]
                    if (is_numeric($item)) {
                        $field_items[] = array("target_id" => $item);
                    }
                    // if [object,object]
                    if (is_object($item) && $item->id()) {
                        $field_items[] = array(
                            'target_id' => $item->id(),
                        );
                    }
                    // if ["12","12","Name"]
                    if (is_string($item)) {

                        $term_exist = $this->is_exits($entity_type, $bundle, $item);
                        if (sizeof($term_exist) == 0) {
                            $term = $this->save(
                                $entity_type,
                                $bundle,
                                [
                                    $key_label => $item,
                                ]
                            );
                            $field_items[] = array(
                                'target_id' => $term->id(),
                            );

                        } else {
                            $field_items[] = array(
                                'target_id' => end($term_exist),
                            );
                        }
                    }
                }
            }
            if (!empty($field_items)) {
                $entity_parent->set($field_name, $field_items);
            }
        }
        return $entity_parent;
    }

    public function entity_reference_user($entity_parent, $field_name, $field_value)
    {
        if (is_array($field_value)) {
            $field_required = [];
            $status = false;
            foreach ($field_value as $item) {
                if (is_numeric($item)) {
                    $field_required[] = $item;
                } else {
                    if (is_array($item) && isset($item["name"]) && isset($item["mail"]) && isset($item["pass"])) {
                        $field_required[] = $item;
                    } else {
                        $status = true;
                    }
                }
            }
            if ($status) {
                \Drupal::messenger()->addMessage('Entity User require fields : name, mail, pass', 'error');
            }
            if (!empty($field_required)) {
                return $this->entity_reference($entity_parent, $field_name, $field_required);
            } else {
                return $entity_parent;
            }
        } else {
            return $this->entity_reference($entity_parent, $field_name, $field_value);
        }
    }

}
