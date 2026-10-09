<?php

/**
 * @file OpenGraphPlugin.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class OpenGraphPlugin
 * @ingroup plugins_generic_openGraph
 *
 * @brief Inject Open Graph meta tags into submission views in OJS, OMP and OPS and issue view in OJS.
 */

namespace APP\plugins\generic\openGraph;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\core\PKPString;
use PKP\facades\Locale;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class OpenGraphPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled($mainContextId)) {
                Hook::add('ArticleHandler::view', $this->submissionView(...));
                Hook::add('PreprintHandler::view', $this->submissionView(...));
                Hook::add('CatalogBookHandler::book', $this->submissionView(...));
                Hook::add('TemplateManager::display', $this->issueView(...));
            }
            return true;
        }
        return false;
    }

    /**
     * Get the name of the settings file to be installed on new context
     * creation.
     * @return string
     */
    public function getContextSpecificPluginSettingsFile()
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * Inject Open Graph metadata into issue landing page view
     * @param $hookName string
     * @param $args array
     * @return boolean
     */
    public function issueView($hookName, $args)
    {
        $template = $args[1];

        if ($template == 'frontend/pages/issue.tpl') {
            $templateMgr = $args[0];
            $request = $this->getRequest();
            $context = $request->getContext();
            $issue = $templateMgr->getTemplateVars('issue');
            if ($issue) {
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->addHeader('openGraphSiteName', '<meta property="og:site_name" content="' . htmlspecialchars($context->getName($context->getPrimaryLocale())) . '"/>');
                $templateMgr->addHeader('openGraphObjectType', '<meta property="og:type" content="website"/>');
                $templateMgr->addHeader('openGraphTitle', '<meta property="og:title" content="' . htmlspecialchars($context->getName($context->getPrimaryLocale())) . " " . htmlspecialchars($issue->getIssueIdentification()) . '"/>');
                $templateMgr->addHeader('openGraphUrl', '<meta property="og:url" content="' . htmlspecialchars($request->url(null, 'issue', 'view', array($issue->getBestIssueId()))) . '"/>');
                $templateMgr->addHeader('openGraphLocale', '<meta property="og:locale" content="' . htmlspecialchars($this->getOpenGraphLocale($context->getPrimaryLocale())) . '"/>');
                if ($issue && $issueCoverImage = $issue->getLocalizedCoverImageUrl()) {
                    $templateMgr->addHeader('openGraphImage', '<meta name="image" property="og:image" content="' . htmlspecialchars($issueCoverImage) . '"/>');
                    $templateMgr->addHeader('twitterCard', '<meta name="twitter:card" content="summary_large_image" />');
                    $templateMgr->addHeader('twitterSiteName', '<meta name="twitter:site" content="' . htmlspecialchars($context->getName($context->getPrimaryLocale())) . '"/>');
                    $templateMgr->addHeader('twitterTitle', '<meta name="twitter:title" content="' . htmlspecialchars($context->getName($context->getPrimaryLocale())) . " " . htmlspecialchars($issue->getIssueIdentification()) . '"/>');
                    $templateMgr->addHeader('twitterImage', '<meta name="twitter:image" content="' . htmlspecialchars($issueCoverImage) . '"/>');
                }
            }
        }

        return false;

    }

    /**
     * Inject Open Graph metadata into submission landing page view
     * @param $hookName string
     * @param $args array
     * @return boolean
     */
    public function submissionView($hookName, $args)
    {
        $application = Application::get();
        $applicationName = $application->getName();
        $request = $args[0];
        $context = $request->getContext();
        if ($applicationName == "ops") {
            $submission = $args[1];
            $publication = $args[2];
            $submissionPath = array('preprint', 'view');
            $objectType = "article";
        } elseif ($applicationName == "omp") {
            $submission = $args[1];
            $publication = $args[2];
            $submissionPath = array('catalog', 'book');
            $objectType = "book";
        } else {
            $issue = $args[1];
            $submission = $args[2];
            $publication = $args[3];
            $submissionPath = array('article', 'view');
            $objectType = "article";
        }
        $publicationLocale = $publication->getData('locale');

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->addHeader('openGraphSiteName', '<meta property="og:site_name" content="' . htmlspecialchars($context->getName($context->getPrimaryLocale())) . '"/>');
        $templateMgr->addHeader('openGraphObjectType', '<meta property="og:type" content="' . htmlspecialchars($objectType) . '"/>');
        $chapter = $applicationName == "omp" ? $args[3] : null;
        $titleSource = $chapter ? $chapter : $publication;
        $abstractSource = $chapter && $chapter->getLocalizedData('abstract', $publicationLocale) ? $chapter : $publication;
        $templateMgr->addHeader('openGraphTitle', '<meta property="og:title" content="' . htmlspecialchars($titleSource->getLocalizedFullTitle($publicationLocale)) . '"/>');
        if ($abstract = PKPString::html2text($abstractSource->getLocalizedData('abstract', $publicationLocale))) {
            $templateMgr->addHeader('openGraphDescription', '<meta name="description" property="og:description" content="' . htmlspecialchars($abstract) . '"/>');
        }
        $templateMgr->addHeader('openGraphUrl', '<meta property="og:url" content="' . htmlspecialchars($request->url(null, $submissionPath[0], $submissionPath[1], $chapter ? array($submission->getBestId(), 'chapter', $chapter->getSourceChapterId()) : array($submission->getBestId()))) . '"/>');
        if ($locale = $publicationLocale) {
            $templateMgr->addHeader('openGraphLocale', '<meta property="og:locale" content="' . htmlspecialchars($this->getOpenGraphLocale($locale)) . '"/>');
        }

        $openGraphImage = "";
        if ($contextPageHeaderLogo = $context->getLocalizedData('pageHeaderLogoImage')) {
            $openGraphImage = $templateMgr->getTemplateVars('publicFilesDir') . "/" . $contextPageHeaderLogo['uploadName'];
        }

        if (isset($issue) && $issueCoverImage = $issue->getLocalizedCoverImageUrl()) {
            $openGraphImage = $issueCoverImage;
        }

        if ($publication->getLocalizedData('coverImage') && $submissionCoverImage = $publication->getLocalizedCoverImageUrl($submission->getData('contextId'))) {
            $openGraphImage = $submissionCoverImage;
        }
        if ($openGraphImage) {
            $templateMgr->addHeader('openGraphImage', '<meta name="image" property="og:image" content="' . htmlspecialchars($openGraphImage) . '"/>');
        }

        $datePublished = $publication->getData('datePublished');
        if ($chapter && $submission->getEnableChapterPublicationDates() && $chapter->getDatePublished()) {
            $datePublished = $chapter->getDatePublished();
        }
        if ($datePublished) {
            $openGraphDateName = $applicationName == "omp" ? "book:release_date" : "article:published_time";
            $templateMgr->addHeader('openGraphDate', '<meta property="' . $openGraphDateName . '" content="' . date('Y-m-d', strtotime($datePublished)) . '"/>');
        }

        if ($applicationName == "omp") {
            $isbns = array();
            $publicationFormats = $publication->getData('publicationFormats');
            foreach ($publicationFormats as $publicationFormat) {
                $identificationCodes = $publicationFormat->getIdentificationCodes();
                while ($identificationCode = $identificationCodes->next()) {
                    if ($identificationCode->getCode() == "02" || $identificationCode->getCode() == "15") {
                        $isbns[$identificationCode->getValue()] = true;
                    }
                }
            }
            $j = 0;
            foreach (array_keys($isbns) as $isbn) {
                $templateMgr->addHeader('openGraphBookIsbn' . $j++, '<meta property="book:isbn" content="' . htmlspecialchars($isbn) . '"/>');
            }
        }

        $i = 0;
        $publicationKeywords = $publication->getData('keywords') ?? array();
        foreach ($publicationKeywords[Locale::getLocale()] ?? array() as $keyword) {
            if (is_array($keyword) && isset($keyword['name'])) {
                $templateMgr->addHeader('openGraphArticleTag' . $i++, '<meta property="' . $objectType . ':tag" content="' . htmlspecialchars($keyword['name']) . '"/>');
            }
        }

        return false;
    }

    /**
     * Default territory of the languages PKP ships without one, after the
     * CLDR likely subtags (pt is the Portugal locale, pt_BR being its own)
     */
    public const LANGUAGE_TERRITORIES = array(
        'an' => 'ES', 'ar' => 'EG', 'az' => 'AZ', 'be' => 'BY', 'bg' => 'BG', 'bs' => 'BA', 'ca' => 'ES',
        'ckb' => 'IQ', 'cnr' => 'ME', 'cs' => 'CZ', 'da' => 'DK', 'de' => 'DE', 'dsb' => 'DE', 'el' => 'GR',
        'en' => 'US', 'es' => 'ES', 'eu' => 'ES', 'fa' => 'IR', 'fi' => 'FI', 'fr' => 'FR', 'gd' => 'GB',
        'gl' => 'ES', 'he' => 'IL', 'hi' => 'IN', 'hr' => 'HR', 'hsb' => 'DE', 'hu' => 'HU', 'hy' => 'AM',
        'id' => 'ID', 'is' => 'IS', 'it' => 'IT', 'ja' => 'JP', 'ka' => 'GE', 'kk' => 'KZ', 'ko' => 'KR',
        'ky' => 'KG', 'lol' => 'CD', 'lt' => 'LT', 'lv' => 'LV', 'mk' => 'MK', 'mn' => 'MN', 'mr' => 'IN',
        'ms' => 'MY', 'nb' => 'NO', 'nl' => 'NL', 'pl' => 'PL', 'ps' => 'AF', 'pt' => 'PT', 'ro' => 'RO',
        'ru' => 'RU', 'se' => 'NO', 'sk' => 'SK', 'sl' => 'SI', 'sq' => 'AL', 'sr' => 'RS', 'sv' => 'SE',
        'sw' => 'TZ', 'th' => 'TH', 'tl' => 'PH', 'tr' => 'TR', 'uk' => 'UA', 'ur' => 'PK', 'uz' => 'UZ',
        'vi' => 'VN',
    );

    /**
     * Convert a PKP locale (en, pt_BR, sr@latin, zh_Hant) to the
     * language_TERRITORY format required by og:locale
     * @param $locale string
     * @return string
     */
    public function getOpenGraphLocale($locale)
    {
        $parts = explode('_', str_replace('-', '_', strtok((string) $locale, '@')));
        $language = strtolower($parts[0]);
        $territory = null;
        foreach (array_slice($parts, 1) as $part) {
            if (preg_match('/^[A-Za-z]{2}$/', $part)) {
                $territory = strtoupper($part);
            }
        }
        if (!$territory && $language == 'zh') {
            $territory = in_array('Hant', $parts) ? 'TW' : 'CN';
        }
        if (!$territory) {
            $territory = self::LANGUAGE_TERRITORIES[$language] ?? null;
        }
        return $territory ? $language . '_' . $territory : $language;
    }

    /**
     * Get the display name of this plugin
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.generic.openGraph.name');
    }

    /**
     * Get the description of this plugin
     * @return string
     */
    public function getDescription()
    {
        return __('plugins.generic.openGraph.description');
    }
}
