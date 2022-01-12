<?php

namespace Drupal\password_locker_updates;

/**
 * @file
 * Helper functions to assist configuration & databases updates during run.
 */

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\search_api\Entity\Index;
use Symfony\Component\Yaml\Yaml;

/**
 * Should only contain helper functions to assist in upgrading the web factory.
 */
class UpdateHelper {

  /**
   * Replace whole config.
   */
  const MODE_REPLACE = 'replace';

  /**
   * Add only the values missing from config.
   */
  const MODE_ADD_MISSING = 'missing';

  /**
   * Add missing values recursively from config.
   */
  const MODE_ADD_MISSING_RECURSIVE = 'missing_recursive';

  /**
   * Merge configs - deep merge.
   */
  const MODE_MERGE = 'merge';

  /**
   * Replace a particular key in config.
   */
  const MODE_REPLACE_KEY = 'replace_key';

  /**
   * Config Storage service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * File system interface.
   *
   * @var \DrupalCore\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Theme Manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * Constructs a new myConfigManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config storage object.
   * @param \DrupalCore\File\FileSystemInterface $file_system
   *   File system interface.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language Manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   Theme Manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module Handler.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger Channel Factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              FileSystemInterface $file_system,
                              LanguageManagerInterface $language_manager,
                              EntityTypeManagerInterface $entity_type_manager,
                              ThemeManagerInterface $theme_manager,
                              ModuleHandlerInterface $module_handler,
                              LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->themeManager = $theme_manager;
    $this->moduleHandler = $module_handler;
    $this->logger = $logger_factory->get('password_locker_updates');
  }

  /**
   * Update Config from code to active storage.
   *
   * @param array $configs
   *   The name of configs to import.
   * @param string $module_name
   *   Name of the module, where files resides.
   * @param string $path
   *   Path where configs reside. Defaults to install.
   * @param string $mode
   *   Mode of update operation replace / add missing.
   * @param array $options
   *   Array of keys to replace when using MODE_REPLACE_KEY.
   */
  public function updateConfigs(array $configs, $module_name, $path = 'install', $mode = self::MODE_REPLACE, array $options = []) {
    if (empty($configs)) {
      return;
    }

    // Skip updating configs for modules currently not installed.
    if (!($this->moduleHandler->moduleExists($module_name))) {
      return;
    }

    foreach ($configs as $config_id) {
      $options['config_name'] = $config_id;

      $config = $this->configFactory->getEditable($config_id);
      $data = $this->getDataFromCode($config_id, $module_name, $path);

      // If block config, replace the theme name with current active theme.
      if (strpos($config_id, 'block.block.') === 0) {
        $data['theme'] = $this->themeManager->getActiveTheme()->getName();
      }

      // If field config.
      if (strpos($config_id, 'field.field.') === 0) {
        $field = FieldConfig::loadByName(
          $data['entity_type'], $data['bundle'], $data['field_name']
        );
        if ($field instanceof FieldConfig) {
          // Update config using config factory.
          $config->setData($data)->save();

          // Load field config again and save again.
          $field = FieldConfig::loadByName(
            $data['entity_type'], $data['bundle'], $data['field_name']
          );
          $field->save();
        }
        // Create field config.
        else {
          FieldConfig::create($data)->save();
        }
      }
      // If field storage.
      elseif (strpos($config_id, 'field.storage.') === 0) {
        $field_storage = FieldStorageConfig::loadByName($data['entity_type'], $data['field_name']);
        if ($field_storage instanceof FieldStorageConfig) {
          $config->setData($data)->save();

          // Load field config again and save again.
          $field_storage = FieldStorageConfig::loadByName($data['entity_type'], $data['field_name']);
          $field_storage->save();
        }
        else {
          $resave_config = FALSE;

          // Some issue with array conversion in allowed values, we handle
          // exception with workaround for now.
          if (isset($data['settings'], $data['settings']['allowed_values']) && !empty($data['settings']['allowed_values'])) {
            $resave_config = TRUE;
            $data['settings']['allowed_values'] = [];
          }

          // Create field storage config.
          FieldStorageConfig::create($data)->save();

          if ($resave_config) {
            // We save it again and now it will go to update config where we
            // do not face issue with allowed values.
            $this->updateConfigs([$config_id], $module_name, $path);
          }
        }
      }
      else {
        $existing = $config->getRawData();
        $existing = is_array($existing) ? $existing : [];
        $updated = $this->getUpdatedData($existing, $data, $mode, $options);
        $config->setData($updated)->save(TRUE);
        $this->configFactory->reset($config_id);
      }

      // Flush image cache for style we updated.
      if (strpos($config_id, 'image.style.') === 0) {
        $style_id = str_replace('image.style.', '', $config_id);

        /** @var \Drupal\image\Entity\ImageStyle $style */
        $style = $this->entityTypeManager->getStorage('image_style')->load($style_id);
        // Using flush() method of ImageStyle entity takes a lot of time as it
        // iterates recursively and deletes each file one by one, deleting
        // the directory using shell cmd is quicker with hook_update.
        $directory = file_url_transform_relative(file_create_url($this->fileSystem->get_default_scheme() . '://styles/' . $style->id()));
        if (file_exists($directory)) {
          $this->logger->info('Removing style directory: @directory.', [
            '@directory' => $directory,
          ]);
          shell_exec(sprintf('rm -rf %s', escapeshellarg(ltrim($directory, '/'))));
        }
        else {
          $this->logger->info('Could not find style directory: @directory to remove.', [
            '@directory' => $directory,
          ]);
        }
      }
      elseif (strpos($config_id, 'search_api.index.') === 0) {
        $index_name = str_replace('search_api.index.', '', $config_id);
        try {
          $index = Index::load($index_name);

          // En-sure we save the index after config change to make sure
          // tables are created properly.
          $index->save();
        }
        catch (\Throwable $e) {
          watchdog_exception('my_config', $e);
        }

        $this->logger->info('Re-saved index @index as config saved.', [
          '@index' => $index_name,
        ]);
      }
    }
  }

  /**
   * Get config data stored in config files inside code.
   *
   * @param string $config_id
   *   Configuration ID.
   * @param string $module_name
   *   Name of the module, where files resides.
   * @param string $path
   *   Path where configs reside. Defaults to install.
   *
   * @return mixed
   *   Array from YAML file.
   */
  public function getDataFromCode($config_id, $module_name, $path) {
    $file = drupal_get_path('module', $module_name) . '/config/' . $path . '/' . $config_id . '.yml';

    if (!file_exists($file)) {
      return '';
    }

    return Yaml::parse(file_get_contents($file));
  }

  /**
   * Get updated data to store in config storage.
   *
   * Use mode as myConfigManager::MODE_ADD_MISSING if you want to add only
   * the newly added configuration values (defaults).
   *
   * @param array $existing
   *   Existing value from config storage.
   * @param array $data
   *   Config data from code.
   * @param string $mode
   *   Mode to use replace/merge.
   * @param array $options
   *   Array of Keys to replace when using MODE_REPLACE_KEY.
   *
   * @return array
   *   Updated data based on mode.
   */
  public function getUpdatedData(array $existing, array $data, $mode, array $options = []) {
    switch ($mode) {
      case self::MODE_ADD_MISSING:
        // For now we check only level one keys. We may want to enhance it
        // later to do recursive check. We may want to complicate this a bit
        // more to handle more scenarios. For now it is simple.
        $data = array_merge($data, $existing);
        break;

      case self::MODE_ADD_MISSING_RECURSIVE:
        // Add Missing keys recursively, Keeping existing data as is.
        $data = NestedArray::mergeDeepArray([$data, $existing], TRUE);
        break;

      case self::MODE_MERGE:
        // Same as $config->merge(). To keep code consistent we do it here.
        $data = NestedArray::mergeDeepArray([$existing, $data], TRUE);
        break;

      case self::MODE_REPLACE_KEY:
        foreach ($options['replace_keys'] as $replace_key) {
          $existing[$replace_key] = $data[$replace_key];
        }
        $data = $existing;
        break;

      default:
        // Do nothing.
        break;
    }

    return $data;
  }

}
