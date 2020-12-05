<?php

namespace EntitySchema\MediaWiki\Content;

use JsonContent;
use ParserOptions;
use ParserOutput;
use Title;
use EntitySchema\Services\SchemaConverter\SchemaConverter;

/**
 * Represents the content of a EntitySchema page
 */
class EntitySchemaContent extends JsonContent {

	const CONTENT_MODEL_ID = 'EntitySchema';

	public function __construct( $text, $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct( $text, $modelId );
	}

	protected function fillParserOutput(
		Title $title,
		$revId,
		ParserOptions $options,
		$generateHtml,
		/** @noinspection ReferencingObjectsInspection */
		ParserOutput &$output
	) {

		if ( $generateHtml && $this->isValid() ) {
			$languageCode = $options->getUserLang();
			$renderer = new EntitySchemaSlotViewRenderer( $languageCode );
			$renderer->fillParserOutput(
				( new SchemaConverter() )
					->getFullViewSchemaData( $this->getText(), [ $languageCode ] ),
				$title,
				$output
			);
		} else {
			$output->setText( '' );
		}
	}

	public function getTextForSearchIndex() {
		$converter = new SchemaConverter();
		$schemaData = $converter->getFullViewSchemaData( $this->getText(), [] );
		$textForSearchIndex = '';

		foreach ( $schemaData->nameBadges as $nameBadge ) {
			if ( $nameBadge->label ) {
				$textForSearchIndex .= $nameBadge->label . "\n";
			}
			if ( $nameBadge->description ) {
				$textForSearchIndex .= $nameBadge->description . "\n";
			}
			if ( $nameBadge->aliases ) {
				$textForSearchIndex .= implode( ', ', $nameBadge->aliases ) . "\n";
			}
		}
		return $textForSearchIndex . $schemaData->schemaText;
	}

}
