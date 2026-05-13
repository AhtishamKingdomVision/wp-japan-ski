<?php

namespace Duplicator\Views\UI;

use Duplicator\Utils\Logging\ErrorHandler;

/**
 * Gets the view state of UI elements to remember its viewable state
 */
class UiViewState
{
    const OPTIONS_TABLE_KEY = 'dupli_opt_ui_view_state';

    /**
     * Save the view state of UI elements
     *
     * @param string $key   A unique key to define the UI element
     * @param mixed  $value A generic value to use for the view state
     *
     * @return bool Returns true if the value was successfully saved
     */
    public static function save(string $key, $value): bool
    {
        $view_state       = get_option(self::OPTIONS_TABLE_KEY, []);
        $view_state[$key] = $value;
        return update_option(self::OPTIONS_TABLE_KEY, $view_state);
    }

    /**
     * Saves the state of a UI element via post params
     *
     * @return void
     *
     * <code>
     * //JavaScript Ajax Request
     * DupliJs.UI.SaveViewStateByPost('dup-pack-archive-panel', 1);
     *
     * //Call PHP Code
     * $view_state       = UiViewState::getValue('dup-pack-archive-panel');
     * $ui_css_archive   = ($view_state == 1)   ? 'display:block' : 'display:none';
     * </code>
     *
     * @todo: Move this method to a controller see dlite (ctrl)
     */
    public static function saveByPost(): void
    {
        ErrorHandler::init();
        check_ajax_referer('duplicator_view_state_update', 'nonce');
        $json      = [
            'update-success' => false,
            'error-message'  => '',
            'key'            => '',
            'value'          => '',
        ];
        $isValid   = true;
        $inputData = filter_input_array(INPUT_POST, [
            'states' => [
                'filter'  => FILTER_UNSAFE_RAW,
                'flags'   => FILTER_FORCE_ARRAY,
                'options' => [
                    'default' => [],
                ],
            ],
            'key'    => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'options' => ['default' => false],
            ],
            'value'  => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'options' => ['default' => false],
            ],
        ]);
        if (isset($inputData['states']) && !empty($inputData['states'])) {
            foreach ($inputData['states'] as $index => $state) {
                $filteredState = filter_var_array($state, [
                    'key'   => [
                        'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                        'options' => ['default' => false],
                    ],
                    'value' => [
                        'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                        'options' => ['default' => false],
                    ],
                ]);
                if ($filteredState['key'] === false && $filteredState['value']) {
                    $isValid = false;
                    break;
                }
                $inputData['states'][$index] = $filteredState;
            }
        }
        if ($inputData['key'] === false || $inputData['value'] === false) {
            $isValid = false;
        }
        // VALIDATIO END

        if ($isValid) {
            if (!empty($inputData['states'])) {
                $view_state = self::getArray();
                $last_key   = '';
                foreach ($inputData['states'] as $state) {
                    $view_state[$state['key']] = $state['value'];
                    $last_key                  = $state['key'];
                }
                $json['update-success'] = self::setArray($view_state);
                $json['key']            = esc_html($last_key);
                $json['value']          = esc_html($view_state[$last_key]);
            } else {
                $json['update-success'] = self::save($inputData['key'], $inputData['value']);
                $json['key']            = esc_html($inputData['key']);
                $json['value']          = esc_html($inputData['value']);
            }
        } else {
            $json['update-success'] = false;
            $json['error-message']  = "Sent data is not valid.";
        }

        die(json_encode($json));
    }

    /**
     *  Gets all the values from the settings array
     *
     *  @return array<string, mixed> Returns and array of all the values stored in the settings array
     */
    public static function getArray(): array
    {
        return get_option(self::OPTIONS_TABLE_KEY, []);
    }

    /**
     * Gwer view statue value or default if don't exists
     *
     * @param string $key     key
     * @param mixed  $default default value
     *
     * @return mixed
     */
    public static function getValue(string $key, $default = false)
    {
        $vals = self::getArray();
        return ($vals[$key] ?? $default);
    }

    /**
     * Sets all the values from the settings array
     *
     * @param array<string, mixed> $view_state states
     *
     * @return boolean Returns whether updated or not
     */
    public static function setArray(array $view_state): bool
    {
        return update_option(self::OPTIONS_TABLE_KEY, $view_state);
    }
}
