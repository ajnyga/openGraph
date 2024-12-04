<?php

/**
 * @file OpenGraphPlugin.inc.php
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

import('lib.pkp.classes.plugins.GenericPlugin');

class OpenGraphPlugin extends GenericPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		if (parent::register($category, $path, $mainContextId)) {
			if ($this->getEnabled($mainContextId)) {
				HookRegistry::register('ArticleHandler::view', array(&$this, 'submissionView'));
				HookRegistry::register('PreprintHandler::view', array(&$this, 'submissionView'));
				HookRegistry::register('CatalogBookHandler::book',array(&$this, 'submissionView'));
				HookRegistry::register('TemplateManager::display',array(&$this, 'issueView'));
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
	function getContextSpecificPluginSettingsFile() {
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * Inject Open Graph metadata into issue landing page view
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function issueView($hookName, $args) {
		$template = $args[1];

		if ($template == 'frontend/pages/issue.tpl') {
			$templateMgr = $args[0];
			$request = $this->getRequest();
			$context = $request->getContext();
			$issue = $templateMgr->getTemplateVars('issue');
			if ($issue){
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->addHeader('openGraphSiteName', '<meta property="og:site_name" content="' . htmlspecialchars($context->getName($context->getPrimaryLocale())) . '"/>');
				$templateMgr->addHeader('openGraphObjectType', '<meta property="og:type" content="website"/>');
				$templateMgr->addHeader('openGraphTitle', '<meta property="og:title" content="' . htmlspecialchars($context->getName($context->getPrimaryLocale())) . " " . htmlspecialchars($issue->getIssueIdentification()) . '"/>');
				$templateMgr->addHeader('openGraphUrl', '<meta property="og:url" content="' . $request->url(null, 'issue', 'view', array($issue->getBestIssueId())) . '"/>');
				$templateMgr->addHeader('openGraphLocale', '<meta property="og:locale" content="' . htmlspecialchars($context->getPrimaryLocale()) . '"/>');
				if ($issue && $issueCoverImage = $issue->getLocalizedCoverImageUrl()){
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
	function submissionView($hookName, $args) {
		$application = Application::get();
		$applicationName = $application->getName();
		$request = $args[0];
		$context = $request->getContext();
		if ($applicationName == "ops"){
			$submission = $args[1];
			$submissionPath = array('preprint', 'view');
			$objectType = "article";
		}
		elseif ($applicationName == "omp"){
			$submission = $args[1];
			$submissionPath = array('catalog', 'book');
			$objectType = "book";
		}
		else {
			$issue = $args[1];
			$submission = $args[2];
			$submissionPath = array('article', 'view');
			$objectType = "article";
		}

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->addHeader('openGraphSiteName', '<meta property="og:site_name" content="' . htmlspecialchars($context->getName($context->getPrimaryLocale())) . '"/>');
		$templateMgr->addHeader('openGraphObjectType', '<meta property="og:type" content="' . htmlspecialchars($objectType) . '"/>');
		$templateMgr->addHeader('openGraphTitle', '<meta property="og:title" content="' . htmlspecialchars($submission->getFullTitle($submission->getLocale())) . '"/>');
		if ($abstract = PKPString::html2text($submission->getAbstract($submission->getLocale()))) $templateMgr->addHeader('openGraphDescription', '<meta name="description" property="og:description" content="' . htmlspecialchars($abstract) . '"/>');
		$templateMgr->addHeader('openGraphUrl', '<meta property="og:url" content="' . $request->url(null, $submissionPath[0], $submissionPath[1], array($submission->getBestId())) . '"/>');
		if ($locale = $submission->getLocale()) $templateMgr->addHeader('openGraphLocale', '<meta name="og:locale" content="' . htmlspecialchars($locale) . '"/>');

		$openGraphImage = "";
		if ($contextPageHeaderLogo = $context->getLocalizedData('pageHeaderLogoImage')){
			$openGraphImage = $templateMgr->getTemplateVars('publicFilesDir') . "/" . $contextPageHeaderLogo['uploadName'];
		}
		if ($issue && $issueCoverImage = $issue->getLocalizedCoverImageUrl()){
			$openGraphImage = $issueCoverImage;
		}
		if ($submissionCoverImage = $submission->getCurrentPublication()->getLocalizedCoverImageUrl($submission->getData('contextId'))){
			$openGraphImage = $submissionCoverImage;
		}
		$templateMgr->addHeader('openGraphImage', '<meta name="image" property="og:image" content="' . htmlspecialchars($openGraphImage) . '"/>');

		if ($datePublished = $submission->getDatePublished()) { 
			$openGraphDateName = $applicationName == "omp" ? "book:release_date" : "article:published_time";
			$templateMgr->addHeader('openGraphDate', '<meta name="' . $openGraphDateName . '" content="' . strftime('%Y-%m-%d', strtotime($datePublished)) . '"/>');
		}

		if ($applicationName == "omp") { 
			$publicationFormats = $submission->getCurrentPublication()->getData('publicationFormats');
			foreach ($publicationFormats as $publicationFormat) {
				$identificationCodes = $publicationFormat->getIdentificationCodes();
				while ($identificationCode = $identificationCodes->next()) {
					if ($identificationCode->getCode() == "02" || $identificationCode->getCode() == "15") {
						$templateMgr->addHeader('openGraphBookIsbn', '<meta name="book:isbn" content="' . htmlspecialchars($identificationCode->getValue()) . '"/>');
					}
				}
			}
		}

		$i=0;
		$dao = DAORegistry::getDAO('SubmissionKeywordDAO');
		$keywords = $dao->getKeywords($submission->getCurrentPublication()->getId(), array(AppLocale::getLocale()));
		foreach ($keywords as $locale => $localeKeywords) {
			foreach ($localeKeywords as $keyword) {
				$templateMgr->addHeader('openGraphArticleTag' . $i++, '<meta name="' . $objectType . ':tag" content="' . htmlspecialchars($keyword) . '"/>');
			}
		}

		return false;
	}

	/**
	 * Get the display name of this plugin
	 * @return string
	 */
	function getDisplayName() {
		return __('plugins.generic.openGraph.name');
	}

	/**
	 * Get the description of this plugin
	 * @return string
	 */
	function getDescription() {
		return __('plugins.generic.openGraph.description');
	}
}