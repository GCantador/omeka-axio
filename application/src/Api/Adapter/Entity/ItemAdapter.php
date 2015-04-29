<?php
namespace Omeka\Api\Adapter\Entity;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\ResourceClass;
use Omeka\Stdlib\ErrorStore;

class ItemAdapter extends AbstractResourceEntityAdapter
{
    /**
     * {@inheritDoc}
     */
    protected $sortFields = array(
        'id'           => 'id',
        'is_public'    => 'isPublic',
        'created'      => 'created',
        'modified'     => 'modified',
    );

    /**
     * {@inheritDoc}
     */
    public function getResourceName()
    {
        return 'items';
    }

    /**
     * {@inheritDoc}
     */
    public function getRepresentationClass()
    {
        return 'Omeka\Api\Representation\Entity\ItemRepresentation';
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityClass()
    {
        return 'Omeka\Entity\Item';
    }

    /**
     * {@inheritDoc}
     */
    public function buildQuery(QueryBuilder $qb, array $query)
    {
        parent::buildQuery($qb, $query);

        if (isset($query['item_set_id']) && is_numeric($query['item_set_id'])) {
            $itemSetAlias = $this->createAlias();
            $qb->innerJoin(
                $this->getEntityClass() . '.itemSets',
                $itemSetAlias
            );
            $qb->andWhere($qb->expr()->eq(
                "$itemSetAlias.id",
                $this->createNamedParameter($qb, $query['item_set_id']))
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        if (array_key_exists('o:item_set', $data)
            && !is_array($data['o:item_set'])
        ) {
            $errorStore->addError('o:item_set', 'Item sets must be an array');
        }

        if (array_key_exists('o:media', $data)
            && !is_array($data['o:media'])
        ) {
            $errorStore->addError('o:item_set', 'Media must be an array');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        parent::hydrate($request, $entity, $errorStore);

        if ($this->shouldHydrate($request, 'o:item_set')) {
            $itemSetsData = $request->getValue('o:item_set', array());

            $itemSetAdapter = $this->getAdapter('item_sets');
            $itemSets = $entity->getItemSets();
            $itemSetsToRetain = array();

            foreach ($itemSetsData as $itemSetData) {
                if (is_array($itemSetData)
                    && array_key_exists('o:id', $itemSetData)
                    && is_numeric($itemSetData['o:id'])
                ) {
                    $itemSetId = $itemSetData['o:id'];
                } elseif (is_numeric($itemSetData)) {
                    $itemSetId = $itemSetData;
                } else {
                    continue;
                }

                if (!$itemSet = $itemSets->get($itemSetId)) {
                    // Item set not already assigned. Assign it.
                    $itemSet = $itemSetAdapter->findEntity($itemSetId);
                    $itemSets->add($itemSet);
                }

                $itemSetsToRetain[] = $itemSet;
            }

            // Unassign item sets that were not included in the passed data.
            foreach ($itemSets as $itemSet) {
                if (!in_array($itemSet, $itemSetsToRetain)) {
                    $itemSets->removeElement($itemSet);
                }
            }
        }

        if ($this->shouldHydrate($request, 'o:media')) {
            $mediasData = $request->getValue('o:media', array());
            $adapter = $this->getAdapter('media');
            $class = $adapter->getEntityClass();
            $retainMedia = array();
            $retainMediaIds = array();
            foreach ($mediasData as $mediaData) {
                if (isset($mediaData['o:id'])) {
                    // Do not update existing media.
                    $retainMediaIds[] = $mediaData['o:id'];
                } else {
                    // Create a new media.
                    $media = new $class;
                    $media->setItem($entity);
                    $subrequest = new Request(Request::CREATE, 'media');
                    $subrequest->setContent($mediaData);
                    $subrequest->setFileData($request->getFileData());
                    $adapter->hydrateEntity($subrequest, $media, $errorStore);
                    $entity->getMedia()->add($media);
                    $retainMedia[] = $media;
                }
            }
            // Remove media not included in request.
            foreach ($entity->getMedia() as $media) {
                if (!in_array($media, $retainMedia, true)
                    && !in_array($media->getId(), $retainMediaIds)
                ) {
                    $entity->getMedia()->removeElement($media);
                }
            }
        }
    }
}
