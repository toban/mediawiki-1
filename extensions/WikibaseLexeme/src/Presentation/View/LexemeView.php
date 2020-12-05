<?php

namespace Wikibase\Lexeme\Presentation\View;

use InvalidArgumentException;
use Language;
use Message;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lexeme\Domain\Model\Lexeme;
use Wikibase\Lexeme\Presentation\Formatters\LexemeTermFormatter;
use Wikibase\View\EntityView;
use Wikibase\View\LanguageDirectionalityLookup;
use Wikibase\View\StatementSectionsView;
use Wikibase\View\Template\TemplateFactory;
use Wikibase\View\ViewContent;
use Wikimedia\Assert\Assert;
use WMDE\VueJsTemplating\Templating;

/**
 * Class for creating HTML views for Lexeme instances.
 *
 * @license GPL-2.0-or-later
 * @author Amir Sarabadani <ladsgroup@gmail.com>
 */
class LexemeView extends EntityView {

	/**
	 * @var FormsView
	 */
	private $formsView;

	/**
	 * @var SensesView
	 */
	private $sensesView;

	/**
	 * @var StatementSectionsView
	 */
	private $statementSectionsView;

	/**
	 * @var EntityIdFormatter
	 */
	private $idFormatter;

	/**
	 * @var LexemeTermFormatter
	 */
	private $lemmaFormatter;

	/**
	 * @var string
	 */
	private $saveMessageKey;

	/**
	 * @param TemplateFactory $templateFactory
	 * @param LanguageDirectionalityLookup $languageDirectionalityLookup
	 * @param string $languageCode
	 * @param FormsView $formsView
	 * @param SensesView $sensesView
	 * @param StatementSectionsView $statementSectionsView
	 * @param LexemeTermFormatter $lemmaFormatter
	 * @param EntityIdFormatter $idFormatter
	 * @param string $saveMessageKey
	 */
	public function __construct(
		TemplateFactory $templateFactory,
		LanguageDirectionalityLookup $languageDirectionalityLookup,
		$languageCode,
		FormsView $formsView,
		SensesView $sensesView,
		StatementSectionsView $statementSectionsView,
		LexemeTermFormatter $lemmaFormatter,
		EntityIdFormatter $idFormatter,
		$saveMessageKey = 'wikibase-save'
	) {
		parent::__construct(
			$templateFactory,
			$languageDirectionalityLookup,
			$languageCode
		);

		$this->formsView = $formsView;
		$this->sensesView = $sensesView;
		$this->statementSectionsView = $statementSectionsView;
		$this->idFormatter = $idFormatter;
		$this->lemmaFormatter = $lemmaFormatter;
		$this->saveMessageKey = $saveMessageKey;
	}

	/**
	 * Builds and returns the main content representing a whole Lexeme
	 *
	 * @param EntityDocument $entity the entity to render
	 * @param int $revision the revision of the entity to render
	 *
	 * @return ViewContent
	 */
	public function getContent( EntityDocument $entity, $revision = null ): ViewContent {
		return new ViewContent(
			$this->renderEntityView( $entity )
		);
	}

	/**
	 * @see EntityView::getMainHtml
	 *
	 * @param EntityDocument $entity
	 *
	 * @throws InvalidArgumentException if the entity type does not match.
	 * @return string HTML
	 */
	protected function getMainHtml( EntityDocument $entity ) {
		/** @var Lexeme $entity */
		Assert::parameterType( Lexeme::class, $entity, '$entity' );
		'@phan-var Lexeme $entity';

		$html = $this->getLexemeHeader( $entity )
			. $this->getLexemeHeaderVueTemplate()
			. $this->getLanguageAndLexicalCategoryVueTemplate()
			. $this->templateFactory->render( 'wikibase-toc' )
			. $this->statementSectionsView->getHtml( $entity->getStatements() )
			. $this->sensesView->getHtml( $entity->getSenses() )
			. $this->formsView->getHtml( $entity->getForms() );

		return $html;
	}

	/**
	 * @param Lexeme $entity
	 * @return string HTML
	 */
	private function getLexemeHeader( Lexeme $entity ) {
		$id = '';
		if ( $entity->getId() ) {
			$id = htmlspecialchars(
				$this->getLocalizedMessage( 'parentheses', [ $entity->getId()->getSerialization() ] )
			);
		}

		$lemmaWidget = $this->renderLemmaWidget( $entity ) . $this->getLemmaVueTemplate();
		$languageAndCategory = $this->renderLanguageAndLexicalCategoryWidget( $entity );

		return <<<HTML
			<div id="wb-lexeme-header" class="wb-lexeme-header">
				<div id="wb-lexeme-header-lemmas">
					<div class="wb-lexeme-header_id">$id</div>
					<div class="wb-lexeme-header_lemma-widget">
						$lemmaWidget
					</div>
				</div>
				$languageAndCategory
			</div>
HTML;
	}

	/**
	 * @see EntityView::getSideHtml
	 *
	 * @param EntityDocument $entity
	 *
	 * @return string HTML
	 */
	protected function getSideHtml( EntityDocument $entity ) {
		return '';
	}

	/**
	 * @param EntityDocument $entity
	 *
	 * @return string
	 */
	public function getTitleHtml( EntityDocument $entity ) {
		/** @var Lexeme $entity */
		Assert::parameterType( Lexeme::class, $entity, '$entity' );
		'@phan-var Lexeme $entity';
		$isEmpty = true;
		$idInParenthesesHtml = '';
		$labelHtml = '';

		if ( $entity->getId() !== null ) {
			$id = $entity->getId()->getSerialization();
			$isEmpty = false;
			$idInParenthesesHtml = htmlspecialchars(
				$this->getLocalizedMessage( 'parentheses', [ $id ] )
			);

			$labelHtml = $this->lemmaFormatter->format( $entity->getLemmas() );
		}

		$title = $isEmpty ? htmlspecialchars(
			$this->getLocalizedMessage( 'wikibase-label-empty' ) ) : $labelHtml;

		return $this->templateFactory->render(
			'wikibase-title',
			$isEmpty ? 'wb-empty' : '',
			$title,
			$idInParenthesesHtml
		);
	}

	/**
	 * @param string $key
	 * @param array $params
	 *
	 * @return string Plain text
	 */
	private function getLocalizedMessage( $key, array $params = [] ) {
		return ( new Message( $key, $params, Language::factory( $this->languageCode ) ) )->text();
	}

	private function getLemmaVueTemplate() {
		return <<<HTML
<script id="lemma-widget-vue-template" type="x-template">
	{$this->getRawLemmaVueTemplate()}
</script>
HTML;
	}

	private function getLexemeHeaderVueTemplate() {
		return <<<HTML
<script id="lexeme-header-widget-vue-template" type="x-template">
	{$this->getRawLexemeHeaderVueTemplate()}
</script>
HTML;
	}

	private function getLanguageAndLexicalCategoryVueTemplate() {
		return <<<HTML
<script id="language-and-lexical-category-widget-vue-template" type="x-template">
	{$this->getRawLanguageAndLexicalCategoryWidgetVueTemplate()}
</script>
HTML;
	}

	private function getRawLexemeHeaderVueTemplate() {
		return <<<HTML
<div id="wb-lexeme-header" class="wb-lexeme-header" v-on:keyup.enter="handleEnter">
	<div id="wb-lexeme-header-lemmas">
		<div class="wb-lexeme-header_id">({{id}})</div><!-- TODO: i18n parentheses -->
		<div class="wb-lexeme-header_lemma-widget">
			<lemma-widget
				:lemmas="lemmas"
				:inEditMode="inEditMode"
				:isSaving="isSaving"
				@hasRedundantLanguage="hasRedundantLemmaLanguage = \$event">
				ref="lemmas"></lemma-widget>
		</div>
		<div class="lemma-widget_controls" v-if="isInitialized" >
			<button type="button" class="lemma-widget_edit" v-if="!inEditMode"
				:disabled="isSaving" v-on:click="edit">{{'wikibase-edit'|message}}</button>
			<button type="button" class="lemma-widget_save" v-if="inEditMode"
				:disabled="isUnsaveable" v-on:click="save">{{'{$this->saveMessageKey}'|message}}</button>
			<button type="button" class="lemma-widget_cancel" v-if="inEditMode"
				:disabled="isSaving"  v-on:click="cancel">{{'wikibase-cancel'|message}}</button>
		</div>
	</div>
	<language-and-category-widget
		:language.sync="language"
		:lexicalCategory.sync="lexicalCategory"
		:inEditMode="inEditMode"
		:isSaving="isSaving"
		ref="languageAndLexicalCategory">
	</language-and-category-widget>
</div>
HTML;
	}

	private function getRawLanguageAndLexicalCategoryWidgetVueTemplate() {
		return <<<'HTML'
<div class="language-lexical-category-widget">
	<div v-if="!inEditMode">
		<div>
			<span>{{'wikibaselexeme-field-language-label'|message}}</span>
			<span class="language-lexical-category-widget_language" v-html="formattedLanguage"></span>
		</div>
		<div>
			<span>{{'wikibaselexeme-field-lexical-category-label'|message}}</span>
			<span class="language-lexical-category-widget_lexical-category"
				v-html="formattedLexicalCategory"></span>
		</div>
	</div>
	<div v-else>
		<div>
			<label for="lexeme-language">{{'wikibaselexeme-field-language-label'|message}}</label>
			<item-selector
				id="lexeme-language"
				v-bind:value="language"
				@input="$emit('update:language', $event)"></item-selector>
		</div>
		<div>
			<label for="lexeme-lexical-category">
				{{'wikibaselexeme-field-lexical-category-label'|message}}
			</label>
			<item-selector
				id="lexeme-lexical-category"
				v-bind:value="lexicalCategory"
				@input="$emit('update:lexicalCategory', $event)"></item-selector>
		</div>
	</div>
</div>
HTML;
	}

	private function getRawLemmaVueTemplate() {
		return <<<'HTML'
<div class="lemma-widget">
	<ul v-if="!inEditMode" class="lemma-widget_lemma-list">
		<li v-for="lemma in lemmaList" class="lemma-widget_lemma">
			<span class="lemma-widget_lemma-value" :lang="lemma.language">{{lemma.value}}</span>
			<span class="lemma-widget_lemma-language">{{lemma.language}}</span>
		</li>
	</ul>
	<div v-else class="lemma-widget_edit-area">
		<ul class="lemma-widget_lemma-list">
			<li v-for="lemma in lemmaList" class="lemma-widget_lemma-edit-box">
				<span class="lemma-widget_lemma-value-label">
					{{'wikibaselexeme-lemma-field-lemma-label'|message}}
				</span>
                <!--
					 In this input, we reverted back to using custom two-way binding
					 instead of using v-model.trim. The reason was that wdio's
					 $(selector).setValue(value) was conflicting with vue's trimming
					 behavior, causing setValue() to append instead of replace text
					 in the input field, causing some false-negatives in browser tests.
                -->
				<input size="1" class="lemma-widget_lemma-value-input"
					:value="lemma.value" :disabled="isSaving"
					@input="lemma.value = $event.target.value.trim()"
				>
				<span class="lemma-widget_lemma-language-label">
					{{'wikibaselexeme-lemma-field-language-label'|message}}
				</span>
				<input size="1" class="lemma-widget_lemma-language-input"
					v-model="lemma.language" :disabled="isSaving"
					:class="{
						'lemma-widget_lemma-language-input_redundant-language':
							isRedundantLanguage(lemma.language)
					}"
					:aria-invalid="isRedundantLanguage(lemma.language)">
				<button class="lemma-widget_lemma-remove" v-on:click="remove(lemma)"
					:disabled="isSaving" :title="'wikibase-remove'|message">
					&times;
				</button>
			</li>
			<li>
				<button type="button" class="lemma-widget_add" v-on:click="add"
					:disabled="isSaving" :title="'wikibase-add'|message">+</button>
			</li>
		</ul>
		<div v-if="hasRedundantLanguage" class="lemma-widget_redundant-language-warning">
			<p>{{'wikibaselexeme-lemma-redundant-language'|message}}</p>
		</div>
	</div>
</div>
HTML;
	}

	/**
	 * @return string
	 */
	private function renderLemmaWidget( Lexeme $lexeme ) {
		$templating = new Templating();

		$lemmas = array_map(
			function ( Term $lemma ) {
				return [ 'value' => $lemma->getText(), 'language' => $lemma->getLanguageCode() ];
			},
			iterator_to_array( $lexeme->getLemmas() )
		);

		$result = $templating->render(
			$this->getRawLemmaVueTemplate(),
			[
				'isInitialized' => false,
				'inEditMode' => false,
				'isSaving' => false,
				'lemmaList' => $lemmas,
				'isUnsaveable' => true
			],
			[
				'message' => function ( $key ) {
					return $this->getLocalizedMessage( $key );
				}
			]
		);

		return '<div id="lemmas-widget">'
			. $result
			. '</div>';
	}

	/**
	 * @param Lexeme $lexeme
	 * @return string
	 */
	private function renderLanguageAndLexicalCategoryWidget( Lexeme $lexeme ) {
		$templating = new Templating();

		$languageId = $lexeme->getLanguage();
		$lexicalCategoryId = $lexeme->getLexicalCategory();

		$result = $templating->render(
			$this->getRawLanguageAndLexicalCategoryWidgetVueTemplate(),
			[
				'isInitialized' => false,
				'inEditMode' => false,
				'isSaving' => false,
				'formattedLanguage' => $this->idFormatter->formatEntityId( $languageId ),
				'language' => $languageId->getSerialization(),
				'formattedLexicalCategory' => $this->idFormatter->formatEntityId(
					$lexicalCategoryId
				),
				'lexicalCategory' => $lexicalCategoryId->getSerialization()
			],
			[
				'message' => function ( $key ) {
					return $this->getLocalizedMessage( $key );
				}
			]
		);

		return '<div>' . $result . '</div>';
	}

}
