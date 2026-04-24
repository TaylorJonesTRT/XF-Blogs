<?php

namespace TaylorJ\Blogs\Option;

use XF\Entity\Option;
use XF\Template\Templater;

use function array_key_exists;

abstract class AbstractOption
{
	/**
	 * @return Templater
	 */
	protected static function getTemplater()
	{
		return \XF::app()->templater();
	}

	protected static function convertChoicesToTemplaterForm(array $choices)
	{
		return static::getTemplater()->mergeChoiceOptions([], $choices);
	}

	protected static function getControlOptions(Option $option, array $htmlParams, $value = null)
	{
		return [
			'name' => $htmlParams['inputName'],
			'value' => $value ?? $option->option_value,
			'type' => $htmlParams['inputType'],
		];
	}

	protected static function getRowOptions(Option $option, array $htmlParams)
	{
		return [
			'label' => $option->title,
			'hint' => $htmlParams['hintHtml'],
			'explain' => $htmlParams['explainHtml'],
			'html' => $htmlParams['listedHtml'],
			'rowclass' => $htmlParams['rowClass'] ?? '',
		];
	}

	protected static function getTextboxRow(Option $option, array $htmlParams, $value = null)
	{
		$controlOptions = static::getControlOptions($option, $htmlParams, $value);
		$rowOptions = static::getRowOptions($option, $htmlParams);

		return static::getTemplater()->formTextBoxRow($controlOptions, $rowOptions);
	}

	protected static function getSelectRow(Option $option, array $htmlParams, array $choices, $value = null)
	{
		$controlOptions = static::getControlOptions($option, $htmlParams, $value);
		$rowOptions = static::getRowOptions($option, $htmlParams);
		$choices = static::convertChoicesToTemplaterForm($choices);

		return static::getTemplater()->formSelectRow($controlOptions, $choices, $rowOptions);
	}

	protected static function getRadioRow(Option $option, array $htmlParams, array $choices, $value = null)
	{
		$controlOptions = static::getControlOptions($option, $htmlParams, $value);
		$rowOptions = static::getRowOptions($option, $htmlParams);
		$choices = static::convertChoicesToTemplaterForm($choices);

		return static::getTemplater()->formRadioRow($controlOptions, $choices, $rowOptions);
	}

	protected static function getCheckboxRow(Option $option, array $htmlParams, array $choices, $value = null)
	{
		$controlOptions = static::getControlOptions($option, $htmlParams, $value);
		$rowOptions = static::getRowOptions($option, $htmlParams);
		$choices = static::convertChoicesToTemplaterForm($choices);

		return static::getTemplater()->formCheckBoxRow($controlOptions, $choices, $rowOptions);
	}

	protected static function getNumberBoxRow(Option $option, array $htmlParams, $value = null)
	{
		$controlOptions = static::getControlOptions($option, $htmlParams, $value);

		foreach (['min', 'max', 'step', 'units'] AS $var)
		{
			if (array_key_exists($var, $htmlParams))
			{
				$controlOptions[$var] = $htmlParams[$var];
			}
		}

		$rowOptions = static::getRowOptions($option, $htmlParams);

		return static::getTemplater()->formNumberBoxRow($controlOptions, $rowOptions);
	}

	protected static function getTemplate($template, Option $option, array $htmlParams, array $extraParams = [])
	{
		$params = array_merge([
			'option' => $option,
			'inputName' => $htmlParams['inputName'],
			'explainHtml' => $htmlParams['explainHtml'],
			'hintHtml' => $htmlParams['hintHtml'],
			'listedHtml' => $htmlParams['listedHtml'],
			'rowClass' => $htmlParams['rowClass'] ?? '',
		], $extraParams);

		return static::getTemplater()->renderTemplate($template, $params);
	}
}
