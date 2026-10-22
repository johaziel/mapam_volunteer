<?php

namespace Drupal\mapam_volunteer\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\flag_search_api\Plugin\search_api\processor\FlagIndexer;
use Psr\Log\LoggerInterface;
use Drupal\flag\FlagService;

/**
 * Adds flag limit comparison.
 *
 * @SearchApiProcessor(
 *   id = "flagging_users_list",
 *   label = @Translation("Flagging users list"),
 *   description = @Translation("Gets information about users who have flagged content"),
 *   stages = {
 *     "add_properties" = 1,
 *     "pre_index_save" = -10
 *   }
 * )
 */
class FlaggingUsersList extends FlagIndexer {

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flagService;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The flag count service.
   *
   * @var \Drupal\flag\FlagCountManagerInterface
   */
  protected $flagCount;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('flag.count'),
      $container->get('flag'),
      $container->get('logger.factory')->get('flag_search_api')

    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $flag_count, FlagService $flagService, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $flagService, $logger);
    $this->flagCount = $flag_count;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['flag_index']['#description'] = $this->t('Index overall limit of users who flagged this content.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getMyOldPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      // Array of flag IDs to check for.
      $properties['flagging_users'] = new ProcessorProperty([
        'label' => $this->t('Flagging Users'),
        'description' => $this->t('String of users who flagged the content separated by |.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ]);

    }

    return $properties;
  }

  /**
   * Helper function for defining our custom fields.
   */
  protected function getFieldsDefinition() {
    $config = $this->configuration['flag_index'];
    $fields = [];
    foreach ($config as $flag) {
      $label = $this->flagService->getFlagById($flag)->get('label');
      $fields['flagging_users_' . $flag . '_list'] = [
        'label' => $this->t('@label list', ['@label' => $label]),
        'description' => $label,
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $config = $this->configuration['flag_index'];
    $flags = $this->flagService->getAllFlags();
    try {
      $entity = $item->getOriginalObject()->getValue();

      foreach ($config as $flag_id) {
        $fields = $this->getFieldsHelper()
          ->filterForPropertyPath($item->getFields(), NULL, 'flagging_users_' . $flag_id . '_list');

        // Add values to the indexed fields.
        foreach ($fields as $flag_field) {
          $users = $this->flagService->getFlaggingUsers($entity, $flags[$flag_id]);
          /** @var \Drupal\user\Entity\User $user */
          $list = [];
          foreach ($users as $user) {
            $list[] = $user->getAccountName();
          }
          $flag_field->addValue(implode('|', $list));
        }
      }
    }
    catch (SearchApiException $exception) {
      $this->logger->error($exception->getMessage());
    }
  }

}
