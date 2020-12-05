<?php

namespace Wikibase\Lexeme\Presentation\View;

use Wikibase\DataModel\Term\Term;
use Wikibase\Lexeme\Domain\Model\Sense;
use Wikibase\Lexeme\Domain\Model\SenseSet;
use Wikibase\Lexeme\MediaWiki\Content\LexemeLanguageNameLookup;
use Wikibase\Lexeme\Presentation\View\Template\LexemeTemplateFactory;
use Wikibase\View\LanguageDirectionalityLookup;
use Wikibase\View\LocalizedTextProvider;
use Wikibase\View\StatementGroupListView;
use WMDE\VueJsTemplating\Templating;

/**
 * @license GPL-2.0-or-later
 */
class SensesView {

	/**
	 * @var LocalizedTextProvider
	 */
	private $textProvider;

	/**
	 * @var LanguageDirectionalityLookup
	 */
	private $languageDirectionalityLookup;

	/**
	 * @var LexemeTemplateFactory
	 */
	private $templateFactory;

	/**
	 * @var StatementGroupListView
	 */
	private $statementGroupListView;

	/**
	 * @var LexemeLanguageNameLookup
	 */
	private $languageNameLookup;

	/**
	 * @param LocalizedTextProvider $textProvider
	 * @param LanguageDirectionalityLookup $languageDirectionalityLookup
	 * @param LexemeTemplateFactory $templateFactory
	 * @param StatementGroupListView $statementGroupListView
	 * @param LexemeLanguageNameLookup $languageNameLookup
	 */
	public function __construct(
		LocalizedTextProvider $textProvider,
		LanguageDirectionalityLookup $languageDirectionalityLookup,
		LexemeTemplateFactory $templateFactory,
		StatementGroupListView $statementGroupListView,
		LexemeLanguageNameLookup $languageNameLookup
	) {
		$this->textProvider = $textProvider;
		$this->languageDirectionalityLookup = $languageDirectionalityLookup;
		$this->templateFactory = $templateFactory;
		$this->statementGroupListView = $statementGroupListView;
		$this->languageNameLookup = $languageNameLookup;
	}

	/**
	 * @param SenseSet $senses
	 *
	 * @return string HTML
	 */
	public function getHtml( SenseSet $senses ) {
		$html = '<div class="wikibase-lexeme-senses-section">';
		$html .= '<h2 class="wb-section-heading section-heading">'
			. '<span class="mw-headline" id="senses">'
			. htmlspecialchars( $this->textProvider->get( 'wikibaselexeme-header-senses' ) )
			. '</span>'
			. '</h2>';

		$html .= '<div class="wikibase-lexeme-senses">';
		foreach ( $senses->toArray() as $sense ) {
			$html .= $this->getSenseHtml( $sense );
		}
		$html .= '</div>';
		$html .= '</div>';
		$html .= $this->getGlossWidgetVueTemplate();

		return $html;
	}

	/**
	 * @param Sense $sense
	 *
	 * @return string HTML
	 */
	private function getSenseHtml( Sense $sense ) {
		$templating = new Templating();

		$glosses = array_map(
			function ( Term $gloss ) {
				return [ 'value' => $gloss->getText(), 'language' => $gloss->getLanguageCode() ];
			},
			iterator_to_array( $sense->getGlosses() )
		);
		ksort( $glosses );

		$glossWidget = $templating->render(
			$this->getRawGlossWidgetTemplate(),
			[
				'senseId' => $sense->getId()->getSerialization(),
				'inEditMode' => false,
				'isSaving' => false,
				'glosses' => $glosses,
				'isUnsaveable' => true
			],
			[
				'message' => function ( $key ) {
					return $this->textProvider->get( $key );
				},
				'directionality' => function ( $languageCode ) {
					return $this->languageDirectionalityLookup->getDirectionality( $languageCode );
				},
				'languageName' => function ( $languageCode ) {
					return $this->languageNameLookup->getName( $languageCode );
				}

			]
		);

		return $this->templateFactory->render(
			'wikibase-lexeme-sense',
			[
				htmlspecialchars( $sense->getId()->getSerialization() ),
				$glossWidget,
				$this->getStatementSectionHtml( $sense ),
				htmlspecialchars( $sense->getId()->getIdSuffix() ),
				htmlspecialchars( $sense->getId()->getSerialization() )
			]
		);
	}

	/**
	 * @param Sense $sense
	 *
	 * @return string HTML
	 */
	private function getStatementSectionHtml( Sense $sense ) {
		$headerText = htmlspecialchars(
			$this->textProvider->get(
				'wikibaselexeme-statementsection-statements-about-sense',
				[ $sense->getId()->getSerialization() ]
			)
		);

		$statementHeader = <<<HTML
<h2 class="wb-section-heading section-heading wikibase-statements" dir="auto">
	<span class="mw-headline">{$headerText}</span>
</h2>
HTML;

		$statementSection = $this->statementGroupListView->getHtml(
			$sense->getStatements()->toArray(), $sense->getId()->getIdSuffix()
		);
		return $statementHeader . $statementSection;
	}

	private function getGlossWidgetVueTemplate() {
		return <<<HTML
<script id="gloss-widget-vue-template" type="x-template">
	{$this->getRawGlossWidgetTemplate()}
</script>
HTML;
	}

	private function getRawGlossWidgetTemplate() {
		return <<<'HTML'
<div class="wikibase-lexeme-sense-glosses">
	<table class="wikibase-lexeme-sense-glosses-table">
		<thead v-if="inEditMode">
			<tr class="wikibase-lexeme-sense-gloss-table-header">
				<td class="wikibase-lexeme-sense-gloss-language">
					{{'wikibaselexeme-gloss-field-language-label'|message}}
				</td>
				<td>{{'wikibaselexeme-gloss-field-gloss-label'|message}}</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<tr v-for="gloss in glosses" class="wikibase-lexeme-sense-gloss">
				<td class="wikibase-lexeme-sense-gloss-language">
					<span v-if="!inEditMode">{{gloss.language|languageName}}</span>
					<language-selector v-else class="wikibase-lexeme-sense-gloss-language-input"
					:class="{
						'wikibase-lexeme-sense-gloss-language-input_redundant-language':
							isRedundantLanguage(gloss.language),
						'wikibase-lexeme-sense-gloss-language-input_invalid-language':
							isInvalidLanguage(gloss.language)
				   }"
				   v-model="gloss.language"
				   :initialCode="gloss.language"
				></language-selector>
				</td>
				<td class="wikibase-lexeme-sense-gloss-value-cell"
					:dir="gloss.language|directionality" :lang="gloss.language">
					<span v-if="!inEditMode" class="wikibase-lexeme-sense-gloss-value">
						{{gloss.value}}
					</span>
					<input v-if="inEditMode" class="wikibase-lexeme-sense-gloss-value-input"
						:value="gloss.value"
						@input="gloss.value = $event.target.value.trim()"
					>
				</td>
				<td class="wikibase-lexeme-sense-gloss-actions-cell">
					<button v-if="inEditMode"
					class="wikibase-lexeme-sense-glosses-control
						wikibase-lexeme-sense-glosses-remove"
					:disabled="glosses.length <= 1"
					v-on:click="remove(gloss)"  type="button">
						{{'wikibase-remove'|message}}
					</button>
				</td>
			</tr>
		</tbody>
		<tfoot v-if="inEditMode">
			<tr>
				<td colspan="3" >
					<div
					v-if="hasRedundantLanguage"
					class="wikibase-lexeme-sense-gloss_redundant-language-warning"
					>
						<p>{{'wikibaselexeme-sense-gloss-redundant-language'|message}}</p>
					</div>
					<div
					v-if="hasInvalidLanguage"
					class="wikibase-lexeme-sense-gloss_invalid-language-warning"
					>
						<p>{{'wikibaselexeme-sense-gloss-invalid-language'|message}}</p>
					</div>
				</td>
			</tr>
			<tr>
				<td>
				</td>
				<td>
					<button type="button"
						class="wikibase-lexeme-sense-glosses-control
							wikibase-lexeme-sense-glosses-add"
						v-on:click="add" >+ {{'wikibase-add'|message}}
					</button>
				</td>
			</tr>
		</tfoot>
	</table>
</div>
HTML;
	}

}
