<?php

namespace Drupal\api_solutions\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure API Solutions allowed origins.
 */
class ApiSolutionsSettingsForm extends ConfigFormBase
{

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return ['api_solutions.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'api_solutions_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('api_solutions.settings');
        $origins = $config->get('allowed_origins') ?: [];

        $form['allowed_origins'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Allowed Origins'),
            '#description' => $this->t('One origin per line (e.g. https://eroso-madagascar.com). These domains will be allowed to make API requests.'),
            '#default_value' => implode("\n", $origins),
            '#rows' => 6,
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $origins_raw = $form_state->getValue('allowed_origins');
        $origins = array_filter(array_map('trim', explode("\n", $origins_raw)));

        $this->config('api_solutions.settings')
            ->set('allowed_origins', array_values($origins))
            ->save();

        parent::submitForm($form, $form_state);
    }

}
