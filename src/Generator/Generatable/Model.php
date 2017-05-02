<?php

namespace CubeSystems\Leaf\Generator\Generatable;

use CubeSystems\Leaf\Generator\Extras\Field;
use CubeSystems\Leaf\Generator\GeneratorFormatter;
use CubeSystems\Leaf\Generator\Schema;
use CubeSystems\Leaf\Generator\Stubable;
use CubeSystems\Leaf\Generator\StubGenerator;
use CubeSystems\Leaf\Generator\Support\Traits\CompilesRelations;
use CubeSystems\Leaf\Services\FieldTypeRegistry;
use CubeSystems\Leaf\Services\StubRegistry;
use Illuminate\Console\DetectsApplicationNamespace;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\App;

class Model extends StubGenerator implements Stubable
{
    use DetectsApplicationNamespace, CompilesRelations;

    /**
     * @var FieldTypeRegistry
     */
    protected $fieldTypeRegistry;

    /**
     * @param StubRegistry $stubRegistry
     * @param Filesystem $filesystem
     * @param GeneratorFormatter $generatorFormatter
     * @param Schema $schema
     */
    public function __construct(
        StubRegistry $stubRegistry,
        Filesystem $filesystem,
        GeneratorFormatter $generatorFormatter,
        Schema $schema
    )
    {
        $this->fieldTypeRegistry = App::make( FieldTypeRegistry::class );

        parent::__construct( $stubRegistry, $filesystem, $generatorFormatter, $schema );
    }

    /**
     * @return string
     */
    public function getCompiledControllerStub(): string
    {
        return $this->stubRegistry->make( 'model', [
            'namespace' => $this->getNamespace(),
            'use' => $this->getCompiledUseClasses(),
            'className' => $this->getClassName(),
            '$tableName' => snake_case( $this->schema->getNamePlural() ),
            'fillable' => $this->getCompiledFillableFields(),
            'relations' => $this->getCompiledRelationMethods()
        ] );
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->formatter->className( $this->schema->getNameSingular() );
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->getClassName() . '.php';
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return rtrim( $this->getAppNamespace(), '\\' );
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return app_path( $this->getFilename() );
    }

    /**
     * @return string
     */
    protected function getCompiledFillableFields(): string
    {
        $fields = $this->schema->getFields()->map( function( Field $field )
        {
            return '\'' . $this->formatter->field( $field->getName() ) . '\',';
        } );

        $fields = $fields->merge( $this->getFillableRelationFields() );

        return $this->formatter->indent( $fields->implode( PHP_EOL ), 2 );
    }

    /**
     * @return string
     */
    protected function getCompiledUseClasses(): string
    {
        return $this->getUseRelations()->implode( PHP_EOL );
    }

    /**
     * @return string
     */
    protected function getCompiledRelationMethods(): string
    {
        if(
            !$this->selectGeneratables->contains( Model::class ) &&
            $this->selectGeneratables->contains( Page::class )
        )
        {
            return (string) null;
        }

        $fields = $this->compileRelationsMethods( $this->schema->getRelations() );

        return $this->formatter->indent( $fields->implode( str_repeat( PHP_EOL, 2 ) ) );
    }
}