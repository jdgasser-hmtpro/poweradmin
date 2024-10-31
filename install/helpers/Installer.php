<?php

/*  Poweradmin, a friendly web-based admin tool for PowerDNS.
 *  See <https://www.poweradmin.org> for more details.
 *
 *  Copyright 2007-2010 Rejo Zenger <rejo@zenger.nl>
 *  Copyright 2010-2024 Poweradmin Development Team
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace PoweradminInstall;

use PoweradminInstall\Validators\AbstractStepValidator;
use PoweradminInstall\Validators\ChooseLanguageValidator;
use PoweradminInstall\Validators\ConfiguringDatabaseValidator;
use PoweradminInstall\Validators\CreateConfigurationFileValidator;
use PoweradminInstall\Validators\CreateLimitedRightsUserValidator;
use PoweradminInstall\Validators\EmptyValidator;
use PoweradminInstall\Validators\GettingReadyValidator;
use PoweradminInstall\Validators\SetupAccountAndNameServersValidator;
use Symfony\Component\HttpFoundation\Request;

class Installer
{
    private Request $request;
    private LocaleHandler $localeHandler;
    private StepValidator $stepValidator;
    private InstallStepHandler $installStepHandler;
    private string $localConfigFile;
    private string $defaultConfigFile;

    private const LOCAL_CONFIG_FILE_PATH = '/inc/config.inc.php';
    private const DEFAULT_CONFIG_FILE_PATH = '/inc/config-defaults.inc.php';

    public function __construct()
    {
        $this->localConfigFile = dirname(__DIR__, 2) . self::LOCAL_CONFIG_FILE_PATH;
        $this->defaultConfigFile = dirname(__DIR__, 2) . self::DEFAULT_CONFIG_FILE_PATH;
        $this->request = Request::createFromGlobals();
        $this->localeHandler = new LocaleHandler();
        $this->stepValidator = new StepValidator();
    }

    public function initialize(): void
    {
        $step = $this->request->get('step', InstallationSteps::STEP_CHOOSE_LANGUAGE);
        $currentStep = $this->stepValidator->getCurrentStep($step);

        $errors = $this->validatePreviousStep($currentStep - 1);

        if ($this->hasLanguageError($errors)) {
            $currentStep = InstallationSteps::STEP_CHOOSE_LANGUAGE;
        }

        // If there are errors, go back to the previous step
        if (!empty($errors)) {
            $currentStep--;
        }

        $currentLanguage = $this->initializeLocaleHandler();
        $twigEnvironment = $this->initializeTwigEnvironment($currentLanguage);

        $this->installStepHandler = new InstallStepHandler($this->request, $twigEnvironment, $currentStep, $currentLanguage);
        $this->installStepHandler->checkConfigFile($this->localConfigFile);

        $this->handleStep($currentStep, $errors);
    }

    private function handleStep(int $currentStep, array $errors): void
    {
        switch ($currentStep) {
            case InstallationSteps::STEP_CHOOSE_LANGUAGE:
                $this->installStepHandler->step1ChooseLanguage();
                break;

            case InstallationSteps::STEP_GETTING_READY:
                $this->installStepHandler->step2GettingReady();
                break;

            case InstallationSteps::STEP_CONFIGURING_DATABASE:
                $this->installStepHandler->step3ConfiguringDatabase($errors);
                break;

            case InstallationSteps::STEP_SETUP_ACCOUNT_AND_NAMESERVERS:
                $this->installStepHandler->step4SetupAccountAndNameServers($this->defaultConfigFile);
                break;

            case InstallationSteps::STEP_CREATE_LIMITED_RIGHTS_USER:
                $this->installStepHandler->step5CreateLimitedRightsUser();
                break;

            case InstallationSteps::STEP_CREATE_CONFIGURATION_FILE:
                $this->installStepHandler->step6CreateConfigurationFile(
                    $this->defaultConfigFile,
                    $this->localConfigFile
                );
                break;

            case InstallationSteps::STEP_INSTALLATION_COMPLETE:
                $this->installStepHandler->step7InstallationComplete();
                break;

            default:
                break;
        }
    }

    private function getStepValidator($step): AbstractStepValidator
    {
        return match ($step) {
            InstallationSteps::STEP_CHOOSE_LANGUAGE => new ChooseLanguageValidator($this->request),
            InstallationSteps::STEP_GETTING_READY => new GettingReadyValidator($this->request),
            InstallationSteps::STEP_CONFIGURING_DATABASE => new ConfiguringDatabaseValidator($this->request),
            InstallationSteps::STEP_SETUP_ACCOUNT_AND_NAMESERVERS => new SetupAccountAndNameServersValidator($this->request),
            InstallationSteps::STEP_CREATE_LIMITED_RIGHTS_USER => new CreateLimitedRightsUserValidator($this->request),
            InstallationSteps::STEP_CREATE_CONFIGURATION_FILE => new CreateConfigurationFileValidator($this->request),
            default => new EmptyValidator($this->request),
        };
    }

    private function validatePreviousStep(int $previousStep): array
    {
        $validator = $this->getStepValidator($previousStep);
        return $validator->validate();
    }

    private function hasLanguageError(array $errors): bool
    {
        if (isset($errors['language'])) {
            ErrorHandler::displayLanguageError();
            return true;
        }
        return false;
    }

    private function initializeLocaleHandler(): string
    {
        $language = $this->request->get('language', LocaleHandler::DEFAULT_LANGUAGE);
        $currentLanguage = $this->localeHandler->getCurrentLanguage($language);
        $this->localeHandler->setLanguage($currentLanguage);
        return $currentLanguage;
    }

    private function initializeTwigEnvironment(string $currentLanguage): \Twig\Environment
    {
        $twigEnvironmentInitializer = new TwigEnvironmentInitializer($this->localeHandler);
        return $twigEnvironmentInitializer->initialize($currentLanguage);
    }
}
