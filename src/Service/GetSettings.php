<?php

namespace App\Service;

use App\Entity\Setting;
use App\Enum\LanguageType;
use App\Enum\SettingName;
use App\Enum\TextEditorName;
use App\Repository\SettingRepository;
use App\Repository\SettingTranslationRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

readonly class GetSettings
{
    public function __construct(
        private SettingRepository $settingRepository,
        private SettingTranslationRepository $settingTranslationRepository,
        private RequestStack $requestStack,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @return array<string, array{value: string, description: string}>|JsonResponse
     */
    public function getSettings(?string $language = null): array|JsonResponse
    {
        // Get the current request from the RequestStack
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            throw new RuntimeException(
                $this->translator->trans('noRequestAvailable', [], 'GetSettings')
            );
        }

        // Allow the user to still be able to change the language
        if ($request->attributes->get('_route') === 'app_change_language') {
            return [];
        }

        // Ignore locale logic for API requests
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return [];
        }

        // Always fetch the latest setting names from the DB
        $allSettings = $this->settingRepository->findAll();

        $locale = $language
            ?? $request->getSession()->get('_locale')
            ?? LanguageType::EN->value;

        $data = [];

        // Fetch translations for the current locale
        $translations = $this->settingTranslationRepository->findBy(['locale' => $locale]);
        $localizedSettings = [];
        foreach ($translations as $translation) {
            $settingName = $translation->getSetting()->getName();
            $localizedSettings[$settingName] = [
                'value' => $translation->getTranslation(),
            ];
        }

        foreach ($allSettings as $setting) {
            $name = $setting->getName();

            $data[$name] = [
                'value' => $localizedSettings[$name]['value'] ?? $setting->getValue(),
                'description' => $this->getSettingDescription($name),
            ];
        }

        $currentSettingsName = array_keys($data);
        $expectedSettings = array_map(static fn($e) => $e->value, SettingName::cases());

        // Compare both sets
        $missingInDb = array_diff($expectedSettings, $currentSettingsName);

        // Check if all the settings on the DB are set and valid
        if ($missingInDb !== []) {
            throw new HttpException(
                500,
                $this->translator->trans(
                    'settingsMissing',
                    ['%missing%' => implode(', ', $missingInDb)],
                    'GetSettings'
                )
            );
        }

        return $data;
    }

    /**
     * @param Setting[] $settings Array of Setting entities
     * @param array<string, array{value: string}> $data Associative array of setting values by name
     *
     * @return Setting[] Array of Setting entities with updated values
     */
    public function getSettingsByLocale(array $settings, array $data): array
    {
        $settingsToTranslate = $this->arraySettingsToTranslate();

        foreach ($settings as $setting) {
            if (in_array($setting->getName(), $settingsToTranslate, true)) {
                $setting->setValue($data[$setting->getName()]['value']);
            }
        }

        return $settings;
    }

    /**
     * Get the description for a given setting name in the current locale.
     */
    public function getSettingDescription(string $settingName): ?string
    {
        // Retrieve current locale from the session, default to 'en' if not found
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            throw new RuntimeException(
                $this->translator->trans('noRequestAvailable', [], 'GetSettings')
            );
        }

        $locale = $request->getSession()->get('_locale') ?: LanguageType::EN->value;

        $translated = $this->translator->trans(
            $settingName,
            [],
            'setting_descriptions',
            $locale
        );

        if ($translated === $settingName) {
            $translated = $this->translator->trans(
                $settingName,
                [],
                'setting_descriptions',
                LanguageType::EN->value
            );
        }

        return $translated !== $settingName ? $translated : null;
    }

    /**
     * @return string[]
     */
    public function arraySettingsToTranslate(): array
    {
        return [
            SettingName::WELCOME_TEXT->value,
            SettingName::WELCOME_DESCRIPTION->value,
            SettingName::ADDITIONAL_LABEL->value,
            SettingName::AUTH_METHOD_SAML_LABEL->value,
            SettingName::AUTH_METHOD_SAML_DESCRIPTION->value,
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_LABEL->value,
            SettingName::AUTH_METHOD_GOOGLE_LOGIN_DESCRIPTION->value,
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_LABEL->value,
            SettingName::AUTH_METHOD_MICROSOFT_LOGIN_DESCRIPTION->value,
            SettingName::AUTH_METHOD_REGISTER_LABEL->value,
            SettingName::AUTH_METHOD_REGISTER_DESCRIPTION->value,
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_LABEL->value,
            SettingName::AUTH_METHOD_LOGIN_TRADITIONAL_DESCRIPTION->value,
            SettingName::AUTH_METHOD_SMS_REGISTER_LABEL->value,
            SettingName::AUTH_METHOD_SMS_REGISTER_DESCRIPTION->value,
        ];
    }

    /**
     * @param string[] $settingsWanted
     *
     * @return array<string, array{value: string}>
     */
    public function getSpecificSettings(array $settingsWanted): array
    {
        $settings = $this->settingRepository->findBy([
            'name' => $settingsWanted,
        ]);

        $result = [];
        foreach ($settings as $setting) {
            /** @var Setting $setting */
            $result[$setting->getName()] = [
                'value' => $setting->getValue(),
            ];
        }

        return $result;
    }
}
