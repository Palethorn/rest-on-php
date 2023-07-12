<?php
namespace RestOnPhp\Handler;

use RestOnPhp\Handler\Response\HandlerResponse;
use RestOnPhp\Metadata\XmlMetadata;

class DocsHandler implements HandlerInterface {
    private $metadata;

    public function __construct(XmlMetadata $metadata) {
        $this->metadata = $metadata;
    }

    public function handle() {
        return new HandlerResponse(HandlerResponse::CARDINALITY_NONE, $this->metadata->getMetadataForDocs());
    }

    public function setFilters($filters) {

    }

    public function setFillers($fillers) {

    }
}
