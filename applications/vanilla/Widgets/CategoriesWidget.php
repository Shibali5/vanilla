<?php
/**
 * @copyright 2009-2021 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace Vanilla\Forum\Widgets;

use Garden\Schema\Schema;
use Vanilla\Site\SiteSectionModel;
use Vanilla\Utility\SchemaUtils;
use Vanilla\Web\JsInterpop\AbstractReactModule;
use Vanilla\Widgets\HomeWidgetContainerSchemaTrait;
use Vanilla\Widgets\WidgetSchemaTrait;
use Vanilla\Widgets\React\CombinedPropsWidgetInterface;
use Vanilla\Widgets\React\CombinedPropsWidgetTrait;

/**
 * Class CategoriesWidget
 */
class CategoriesWidget extends AbstractReactModule implements CombinedPropsWidgetInterface {

    use CombinedPropsWidgetTrait;
    use HomeWidgetContainerSchemaTrait;
    use WidgetSchemaTrait;

    /** @var \CategoriesApiController */
    private $api;

    /**
     * CategoriesWidget constructor.
     *
     * @param \CategoriesApiController $api
     * @param SiteSectionModel $siteSectionModel
     */
    public function __construct(
        \CategoriesApiController $api,
        SiteSectionModel $siteSectionModel
    ) {
        $this->api = $api;
        $this->currentSiteSection = $siteSectionModel->getCurrentSiteSection();
        parent::__construct();
    }

    /**
     * @inheridoc
     */
    public static function getWidgetID(): string {
        return "categories";
    }

    /**
     * @inheridoc
     */
    public static function getWidgetName(): string {
        return "Categories";
    }

    /**
     * @inheridoc
     */
    public function getComponentName(): string {
        return "CategoriesWidget";
    }

    /**
     * Get props for component
     *
     * @return array
     */
    public function getProps(): ?array {
        $validatedParams = $this->getApiSchema()->validate((array)$this->props['apiParams']);
        $this->props['apiParams'] = array_merge((array)$this->props['apiParams'], $validatedParams);

        //if there is manual siteSection filter or we are in siteSection currently, we include it
        $contextualCategoryID = $this->currentSiteSection->getAttributes()['categoryID'] ?? -1;
        $parentCategoryID = $this->props['apiParams']['parentCategoryID'] ?? null;
        $categoryID = $this->props['apiParams']['categoryID'] ?? null;
        if (!$parentCategoryID && !$categoryID) {
            $this->props['apiParams']['parentCategoryID'] = $contextualCategoryID;
        }

        //get the categories
        $categories = $this->api->index($this->props['apiParams'])->getData();

        $this->props['itemData'] = array_map(function ($category) {
            return [
                'to' => $category['url'],
                'iconUrl' => $category['iconUrl'] ?? null,
                'imageUrl' => $category['bannerUrl'] ?? null,
                'name' => $category['name'],
                'description' => $category['description'] ?? '',
                'counts' => [
                    [
                        'labelCode' => 'discussions',
                        'count' => (int)$category['countAllDiscussions'] ?? 0,
                    ],
                ],
            ];
        }, $categories);

        return $this->props;
    }

    /**
     * Get the schema of our api params.
     *
     * @return Schema
     */
    public static function getApiSchema(): Schema {
        $apiSchema = new Schema([
            'type' => 'object',
            'default' => new \stdClass(),
            'description' => 'Api parameters for categories endpoint.',
        ]);
        $apiSchema = $apiSchema->merge(SchemaUtils::composeSchemas(
            Schema::parse([
                'categoryID?' => [
                    'type' => ['string', 'integer', 'null'],
                    'description' => 'One or range of categoryIDs',
                ],
                'limit?' => [ //does not seem like currently its supported for categories without any other filter
                    'type' => 'integer',
                    'default' => 10,
                    'description' => 'Number of results to fetch.',
                ],
                'featured?' => [
                    'type' => 'boolean',
                    'description' => 'Featured categories filter',
                ],
                'followed?' => [
                    'type' => 'boolean',
                    'description' => 'Followed categories filter',
                ],
                'parentCategoryID?' => [
                    'type' => ['string', 'integer', 'null'],
                    'description' => 'Filter by subcommunity',
                ],
                //TODO  sort options ? looks like some changes/adjustments should be done in the api itself, cause right now there is no such parameter to send
            ])
        ));

        return $apiSchema;
    }


    /**
     * @inheridoc
     */
    public static function getWidgetSchema(): Schema {
        $schema = SchemaUtils::composeSchemas(
            self::widgetTitleSchema(),
            self::widgetDescriptionSchema(),
            self::widgetSubtitleSchema('subtitle'),
            self::containerOptionsSchema('containerOptions'),
            self::itemOptionsSchema('itemOptions'),
            Schema::parse([
                'apiParams' => self::getApiSchema(),
            ])
        );

        return $schema;
    }
}
