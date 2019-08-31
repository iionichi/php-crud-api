<?php

namespace Tqdev\PhpCrudApi\OpenApi;

use Tqdev\PhpCrudApi\Column\ReflectionService;
use Tqdev\PhpCrudApi\Middleware\Communication\VariableStore;
use Tqdev\PhpCrudApi\OpenApi\OpenApiDefinition;

class OpenApiColumnsBuilder
{
    private $openapi;
    private $operations = [
        'database' => [
            'read' => 'get',
        ],
        'table' => [
            'create' => 'post',
            'read' => 'get',
            'update' => 'put', //rename
            'delete' => 'delete',
        ],
        'column' => [
            'create' => 'post',
            'read' => 'get',
            'update' => 'put',
            'delete' => 'delete',
        ]
    ];

    public function __construct(OpenApiDefinition $openapi)
    {
        $this->openapi = $openapi;
    }

    public function build() /*: void*/
    {
        $this->setPaths();
        $this->openapi->set("components|responses|boolSuccess|description", "boolean indicating success or failure");
        $this->openapi->set("components|responses|boolSuccess|content|application/json|schema|type", "boolean");
        $this->setComponentSchema();
        $this->setComponentResponse();
        $this->setComponentRequestBody();
        $this->setComponentParameters();
        foreach (array_keys($this->operations) as $index => $type) {
            $this->setTag($index, $type);
        }
    }

    private function setPaths() /*: void*/
    {
        foreach (array_keys($this->operations) as $type) {
            foreach ($this->operations[$type] as $operation => $method) {
                $parameters = [];
                switch ($type) {
                    case 'database':
                        $path = '/columns';
                        break;
                    case 'table':
                        $path = $operation == 'create' ? '/columns' : '/columns/{table}';
                        break;
                    case 'column':
                        $path = $operation == 'create' ? '/columns/{table}' : '/columns/{table}/{column}';
                        break;
                }
                if (strpos($path, '{table}')) {
                    $parameters[] = 'table';
                }
                if (strpos($path, '{column}')) {
                    $parameters[] = 'column';
                }
                foreach ($parameters as $p => $parameter) {
                    $this->openapi->set("paths|$path|$method|parameters|$p|\$ref", "#/components/parameters/$parameter");
                }
                $operationType = $operation . ucfirst($type);
                if (in_array($operation, ['create', 'update'])) {
                    $this->openapi->set("paths|$path|$method|requestBody|\$ref", "#/components/requestBodies/$operationType");
                }
                $this->openapi->set("paths|$path|$method|tags|0", "$type");
                $this->openapi->set("paths|$path|$method|description", "$operation $type");
                switch ($operation) {
                    case 'read':
                        $this->openapi->set("paths|$path|$method|responses|200|\$ref", "#/components/responses/$operationType");
                        break;
                    case 'create':
                    case 'update':
                    case 'delete':
                        $this->openapi->set("paths|$path|$method|responses|200|\$ref", "#/components/responses/boolSuccess");
                        break;
                }
            }
        }
    }

    private function setComponentSchema() /*: void*/
    {
        foreach (array_keys($this->operations) as $type) {
            foreach (array_keys($this->operations[$type]) as $operation) {
                if ($operation == 'delete') {
                    continue;
                }
                $operationType = $operation . ucfirst($type);
                $prefix = "components|schemas|$operationType";
                $this->openapi->set("$prefix|type", "object");
                $this->openapi->set("$prefix|properties|name|type", 'string');
                $this->openapi->set("$prefix|properties|type|type", 'string');
            }
        }
    }

    private function setComponentResponse() /*: void*/
    {
        foreach (array_keys($this->operations) as $type) {
            foreach (array_keys($this->operations[$type]) as $operation) {
                if ($operation != 'read') {
                    continue;
                }
                $operationType = $operation . ucfirst($type);
                $this->openapi->set("components|responses|$operationType|description", "single $type record");
                $this->openapi->set("components|responses|$operationType|content|application/json|schema|\$ref", "#/components/schemas/$operationType");
            }
        }
    }

    private function setComponentRequestBody() /*: void*/
    {
        foreach (array_keys($this->operations) as $type) {
            foreach (array_keys($this->operations[$type]) as $operation) {
                if (!in_array($operation, ['create', 'update'])) {
                    continue;
                }
                $operationType = $operation . ucfirst($type);
                $this->openapi->set("components|requestBodies|$operationType|description", "single $type record");
                $this->openapi->set("components|requestBodies|$operationType|content|application/json|schema|\$ref", "#/components/schemas/$operationType");
            }
        }
    }


    private function setComponentParameters() /*: void*/
    {
        $this->openapi->set("components|parameters|table|name", "table");
        $this->openapi->set("components|parameters|table|in", "path");
        $this->openapi->set("components|parameters|table|schema|type", "string");
        $this->openapi->set("components|parameters|table|description", "table name");
        $this->openapi->set("components|parameters|table|required", true);

        $this->openapi->set("components|parameters|column|name", "column");
        $this->openapi->set("components|parameters|column|in", "path");
        $this->openapi->set("components|parameters|column|schema|type", "string");
        $this->openapi->set("components|parameters|column|description", "column name");
        $this->openapi->set("components|parameters|column|required", true);
    }

    private function setTag(int $index, string $type) /*: void*/
    {
        $this->openapi->set("tags|$index|name", "$type");
        $this->openapi->set("tags|$index|description", "$type operations");
    }
}