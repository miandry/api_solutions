<?php

namespace Drupal\api_solutions;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\drupal_helper\DrupalHelper;
use Drupal\entity_parser\EntityParser;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;

/**
 * Same concept as Entity Parser  module.
 *
 * @see https://www.drupal.org/project/entity_parser
 */
class ApiJsonParser extends EntityParser
{

    //@var json main content list
    public $utility;

    public function __construct()
    {

        $this->utility = new DrupalHelper();
    }
    /**
     * Json builder function.
     *
     * @return array
     */

    public function jsonBuilder($url, $options)
    {
        return $this->urlMapper($url, $options);
    }
    public function generateToken($user)
    {
        if (!is_object($user)) {
            return false;
        }
        $hashed_password = $user->getPassword();
        $token_new = \Drupal\Component\Utility\Crypt::hashBase64($hashed_password);
        return $token_new;
    }
    public function isTokenValid($name, $token)
    {
        $user = user_load_by_name($name);
        if (!is_object($user)) {
            return false;
        }
        $hashed_password = $user->getPassword();
        $token_new = \Drupal\Component\Utility\Crypt::hashBase64($hashed_password);
        return ($token_new == $token);
    }
    public function isUserNameExist($name)
    {
        $query = \Drupal::entityQuery('user')
            ->condition('name', $name);
        $query->range(0, 1);
        $result = $query->execute();
        if (!empty($result)) {
            return true;
        }
        return false;
    }
    public function getFieldExclude($options, $exclude = [])
    {
        $options["#fields_exclude"] = [
            'path',
            'revision_log',
            'revision_translation_affected',
            'panels_display',
            'uuid',
            'revision_timestamp',
            'revision_uid',
            '_deleted',
            '_rev',
            'menu_link',
            'breadcrumb',
            'pass',
            'access',
            'revision_translation_affected',
            'revision_default',
            'revision_id',
            'revision_default',
            'behavior_settings',
            'revision_uid',
            'breadcrumb_display',
            'deploy_flag',
            'deploy_status',
            'panelizer',
            'style',
            'owners',
            'owner_user',
            'workspace',
            'archived',
            'access',
        ];
        $options["#fields_exclude"] = array_merge($options["#fields_exclude"], $exclude);
        return $options;
    }
    public function urlMapper($url, $options)
    {
        // $options['#entity_parser_extend'] = 'Drupal\base\APIFormatter';
        if ($options['#entity_parser_extend']) {
            $parser = new $options['#entity_parser_extend']();
        } else {
            $parser = $this;
        }
        $options["#fields_exclude"] = [
            'path',
            'revision_log',
            'revision_translation_affected',
            'panels_display',
            'uuid',
            'revision_timestamp',
            'revision_uid',
            '_deleted',
            '_rev',
            'menu_link',
            'breadcrumb',
            'pass',
            'access',
            'revision_translation_affected',
            'revision_default',
            'revision_id',
            'revision_default',
            'behavior_settings',
            'revision_uid',
            'breadcrumb_display',
            'deploy_flag',
            'deploy_status',
            'panelizer',
            'style',
            'owners',
            'owner_user',
            'workspace',
            'archived',
            'access',
        ];
        $entity = null;
        $entity = $this->getNodeByAlias($url);
        if (is_object($entity)) {
            $entity_type = $entity->getEntityTypeId();
            $entity = $this->loader_entity_by_type($entity, $entity_type);
        } else {
            $url_array = explode('/', $url);
            if (is_array($url_array) && sizeof($url_array) == 2) {
                $id = end($url_array);
                $entity_type = reset($url_array);
                $entity = $this->loader_entity_by_type($id, $entity_type);
            }
        }
        return $entity;
    }

    /**
     * Get node by url alias.
     *
     * @param string $alias
     * @return \Drupal\Core\Entity\EntityInterface|NULL|NULL
     */
    public function getNodeByAlias($alias)
    {
        /** @var \Drupal\Core\Path\AliasManager $alias_manager */
        $alias_manager = \Drupal::service('path.alias_manager');
        $parts = explode('+', $alias);
        $alias = implode('/', $parts);

        $node_storage = \Drupal::entityTypeManager()->getStorage('node');
        try {
            $path = $alias_manager->getPathByAlias($alias);
            $route = Url::fromUserInput($path);
            if ($route && $route->isRouted()) {
                $params = $route->getRouteParameters();
                if (!empty($params['node'])) {
                    return $node_storage->load($params['node']);
                }
            }
        } catch (\Exception $e) {
            return null;
        }
        return null;
    }
    public function image_file($entity, $field, $style = null)
    {
        $bool = \Drupal::service('drupal.helper')->helper->is_field_ready($entity, $field);
        $img_result = [];

        if ($bool) {
            $images = $entity->get($field)->getValue();
            foreach ($images as $key => $image) {
                $file = File::load($image['target_id']);
                if (is_object($file)) {

                    $img = $image;
                    if ($style) {
                        $style_object = ImageStyle::load($style);
                        if (is_object($style_object)) {
                            $img['image'] = $style_object->buildUrl($file->getFileUri());
                        }
                    }
                    $img['uri'] = $file->getFileUri();
                    $img['url'] = file_create_url($file->getFileUri());
                    $img_result[] = $img;
                }

            }
            $is_multple = $entity->get($field)->getFieldDefinition()->getFieldStorageDefinition()->isMultiple();
            if (!$is_multple && count($img_result) == 1) {
                return array_shift($img_result);
            }
        }
        return $img_result;
    }
    public function imageFullUrl($entity, $field)
    {
        $bool = \Drupal::service('drupal.helper')->helper->is_field_ready($entity, $field);
        if ($bool) {
            $body = $entity->{$field}->value;
            global $base_url;
            $dom = Html::load($body);
            foreach ($dom->getElementsByTagName('img') as $img) {
                $src = $img->getAttribute('src');
                $img->setAttribute("src", $base_url . $src);
            }
            $body = $dom->saveHTML();
            $json = preg_replace('~<(?:!DOCTYPE|/?(?:html|head|body|meta))[^>]*>\s*~i', '', $body);
            return $json;
        }

        return [];

    }
    public function formatTermManager($tid, $param)
    {
        $options = [];
        $options = $this->getFieldExclude($options, ['revision_created', 'default_langcode']);
        //taxonomy_term
        $taxonomy_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
        $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
        if ($taxonomy_term && method_exists($taxonomy_term, 'hasTranslation') && $taxonomy_term->hasTranslation($language)) {
            $taxonomy_term = $taxonomy_term->getTranslation($language);
        }
        $term = false;
        if (is_object($taxonomy_term)) {
            $term = $this->parser($taxonomy_term, [], $options);
            $term1 = [];
            foreach ($param as $key => $value) {
                if ($key == 'included') {
                    foreach ($value as $key_included => $value_included) {
                        if (isset($term[$value_included]['#object'])) {
                            $term1[$value_included] = $this->parser($term[$value_included]['#object'], [], $options);
                        } elseif (isset($term[$value_included]) && is_array($term[$value_included])) {
                            foreach ($term[$value_included] as $multiple_included) {
                                $term1[$value_included][] = $this->parser($multiple_included['#object'], [], $options);
                            }
                        }
                        if (isset($term1[$value_included])) {
                            $term[$value_included] = $term1[$value_included];
                        }
                    }
                }
            }

        }
        return $term;
    }
    public function taxonomy_load_multi_by_vid($vid, $param = null)
    {

        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('vid', $vid);
        $query->sort('weight');
        $res = $query->execute();
        $items = [];
        foreach ($res as $key => $tid) {
            $item = $this->formatTermManager($tid, $param);
            if ($item) {
                $items[] = $item;
            }
        }
        return $items;
    }

    public function listQuery($entityQuery, $entitype, $bundle)
    {


        $filters = \Drupal::request()->get('filters');

        $key_bundle = \Drupal::entityTypeManager()->getDefinition($entitype)->getKey('bundle');
        $query = $entityQuery->condition($key_bundle, $bundle);
        if ($filters) {
            foreach ($filters as $key => $filter) {
                if (isset($filter['op']) && $filter['op'] != null) {
                    $query->condition($key, $filter['val'], $filter['op']);
                } else {
                    if (is_array($filter['val'])) {
                        $query->condition($key, $filter['val'], 'IN');
                    } else {
                        $query->condition($key, $filter['val']);
                    }
                }
            }
        }


        return $query;

    }
    public function listQueryExecute($entitype, $bundle)
    {
        $queryMain = \Drupal::entityQuery($entitype);
        $queryTotal = \Drupal::entityQuery($entitype);


        $pager = \Drupal::request()->get('pager');
        $offset = \Drupal::request()->get('offset');
        $fields = \Drupal::request()->get('fields');
        $changes = \Drupal::request()->get('changes'); // change name field ouput
        $values = \Drupal::request()->get('values'); // change name field ouput
        $sort = \Drupal::request()->get('sort');


        $query = $this->listQuery($queryMain, $entitype, $bundle);
        $query_total = $this->listQuery($queryTotal, $entitype, $bundle);
        $total = $query_total->count()->execute();

        if ($offset == null) {
            $offset = 10;
        }
        if ($sort) {
            $query->sort($sort['val'], $sort['op']);
        }
        if ($pager) {
            if ($pager == 'all') {
            } else {
                $query->range($offset * ($pager), $offset);
            }
        } else {
            $query->range(0, $offset);
        }

        $json = $query->execute();


        return ["rows" => $json, "total" => $total];

    }

}
