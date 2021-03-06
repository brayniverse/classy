<?php

declare(strict_types=1);

/**
 * Copyright (c) 2017 Andreas Möller.
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/localheinz/classy
 */

namespace Localheinz\Classy\Test\Unit;

use Localheinz\Classy\Construct;
use Localheinz\Classy\Constructs;
use Localheinz\Classy\Exception;
use Localheinz\Classy\Test\Fixture;
use PHPUnit\Framework;

final class ConstructsTest extends Framework\TestCase
{
    /**
     * @var string
     */
    private $fileWithParseError = __DIR__ . '/../Fixture/ParseError/MessedUp.php';

    protected function setUp()
    {
        \file_put_contents($this->fileWithParseError, $this->sourceTriggeringParseError());
    }

    protected function tearDown()
    {
        \unlink($this->fileWithParseError);
    }

    public function testFromSourceThrowsParseErrorIfParseErrorIsThrownDuringParsing()
    {
        $source = $this->sourceTriggeringParseError();

        $this->expectException(Exception\ParseError::class);

        Constructs::fromSource($source);
    }

    /**
     * @dataProvider providerSourceWithoutClassyConstructs
     *
     * @param string $source
     */
    public function testFromSourceReturnsEmptyArrayIfNoClassyConstructsHaveBeenFound(string $source)
    {
        $this->assertEquals([], Constructs::fromSource($source));
    }

    public function providerSourceWithoutClassyConstructs(): \Generator
    {
        foreach ($this->casesWithoutClassyConstructs() as $key => $fileName) {
            yield $key => [
                \file_get_contents($fileName),
            ];
        }
    }

    /**
     * @dataProvider providerSourceWithClassyConstructs
     *
     * @param string   $source
     * @param string[] $constructs
     */
    public function testFromSourceReturnsArrayOfClassyConstructsSortedByName(string $source, array $constructs)
    {
        $this->assertEquals($constructs, Constructs::fromSource($source));
    }

    public function providerSourceWithClassyConstructs(): \Generator
    {
        foreach ($this->casesWithClassyConstructs() as $key => list($fileName, $names)) {
            \sort($names);

            yield $key => [
                \file_get_contents($fileName),
                \array_map(function (string $name) {
                    return Construct::fromName($name);
                }, $names),
            ];
        }
    }

    public function testFromDirectoryThrowsDirectoryDoesNotExistIfDirectoryDoesNotExist()
    {
        $directory = __DIR__ . '/NonExistent';

        $this->expectException(Exception\DirectoryDoesNotExist::class);

        Constructs::fromDirectory($directory);
    }

    public function testFromDirectoryThrowsParseErrorIfParseErrorIsThrownDuringParsing()
    {
        $directory = __DIR__ . '/../Fixture/ParseError';

        $this->expectException(Exception\ParseError::class);

        Constructs::fromDirectory($directory);
    }

    /**
     * @dataProvider providerDirectoryWithoutClassyConstructs
     *
     * @param string $directory
     */
    public function testFromDirectoryReturnsEmptyArrayIfNoClassyConstructsHaveBeenFound(string $directory)
    {
        $this->assertCount(0, Constructs::fromDirectory($directory));
    }

    public function providerDirectoryWithoutClassyConstructs(): \Generator
    {
        foreach ($this->casesWithoutClassyConstructs() as $key => $fileName) {
            yield $key => [
                \dirname($fileName),
            ];
        }
    }

    /**
     * @dataProvider providerDirectoryWithClassyConstructs
     *
     * @param string   $directory
     * @param string[] $classyConstructs
     */
    public function testFromDirectoryReturnsArrayOfClassyConstructsSortedByName(string $directory, array $classyConstructs = [])
    {
        $this->assertEquals($classyConstructs, Constructs::fromDirectory($directory));
    }

    public function providerDirectoryWithClassyConstructs(): \Generator
    {
        foreach ($this->casesWithClassyConstructs() as $key => list($fileName, $names)) {
            \sort($names);

            yield $key => [
                \dirname($fileName),
                \array_map(function (string $name) use ($fileName) {
                    return Construct::fromName($name)->definedIn(\realpath($fileName));
                }, $names),
            ];
        }
    }

    public function testFromDirectoryTraversesDirectoriesAndReturnsArrayOfClassyConstructsSortedByName()
    {
        $directory = __DIR__ . '/../Fixture/Traversal';

        $classyConstructs = [
            Construct::fromName(Fixture\Traversal\Foo::class)->definedIn(\realpath($directory . '/Foo.php')),
            Construct::fromName(Fixture\Traversal\Foo\Bar::class)->definedIn(\realpath($directory . '/Foo/Bar.php')),
            Construct::fromName(Fixture\Traversal\Foo\Baz::class)->definedIn(\realpath($directory . '/Foo/Baz.php')),
        ];

        $this->assertEquals($classyConstructs, Constructs::fromDirectory($directory));
    }

    public function testFromDirectoryThrowsMultipleDefinitionsFoundIfMultipleDefinitionsOfSameConstructHaveBeenFound()
    {
        $directory = __DIR__ . '/../Fixture/MultipleDefinitions';

        $this->expectException(Exception\MultipleDefinitionsFound::class);

        Constructs::fromDirectory($directory);
    }

    private function casesWithoutClassyConstructs(): array
    {
        return [
            'no-php-file' => __DIR__ . '/../Fixture/NoClassy/NoPhpFile/source.md',
            'with-anonymous-class' => __DIR__ . '/../Fixture/NoClassy/WithAnonymousClass/source.php',
            'with-anonymous-class-and-multi-line-comments' => __DIR__ . '/../Fixture/NoClassy/WithAnonymousClassAndMultiLineComments/source.php',
            'with-anonymous-class-and-shell-style-comments' => __DIR__ . '/../Fixture/NoClassy/WithAnonymousClassAndShellStyleComments/source.php',
            'with-anonymous-class-and-single-line-comments' => __DIR__ . '/../Fixture/NoClassy/WithAnonymousClassAndSingleLineComments/source.php',
            'with-class-keyword' => __DIR__ . '/../Fixture/NoClassy/WithClassKeyword/source.php',
            'with-nothing' => __DIR__ . '/../Fixture/NoClassy/WithNothing/source.php',
        ];
    }

    private function casesWithClassyConstructs(): array
    {
        return [
            'within-namespace' => [
                __DIR__ . '/../Fixture/Classy/WithinNamespace/source.php',
                [
                    'Foo\\Bar\\Baz\\Bar',
                    'Foo\\Bar\\Baz\\Baz',
                    'Foo\\Bar\\Baz\\Foo',
                ],
            ],
            'within-namespace-and-shell-style-comments' => [
                __DIR__ . '/../Fixture/Classy/WithinNamespaceAndShellStyleComments/source.php',
                [
                    'Foo\\Bar\\Baz\\Bar',
                    'Foo\\Bar\\Baz\\Baz',
                    'Foo\\Bar\\Baz\\Foo',
                ],
            ],
            'within-namespace-and-single-line-comments' => [
                __DIR__ . '/../Fixture/Classy/WithinNamespaceAndSingleLineComments/source.php',
                [
                    'Foo\\Bar\\Baz\\Bar',
                    'Foo\\Bar\\Baz\\Baz',
                    'Foo\\Bar\\Baz\\Foo',
                ],
            ],
            'within-namespace-and-multi-line-comments' => [
                __DIR__ . '/../Fixture/Classy/WithinNamespaceAndMultiLineComments/source.php',
                [
                    'Foo\\Bar\\Baz\\Bar',
                    'Foo\\Bar\\Baz\\Baz',
                    'Foo\\Bar\\Baz\\Foo',
                ],
            ],
            'within-namespace-with-braces' => [
                __DIR__ . '/../Fixture/Classy/WithinNamespaceWithBraces/source.php',
                [
                    'Foo\\Bar\\Baz\\Bar',
                    'Foo\\Bar\\Baz\\Baz',
                    'Foo\\Bar\\Baz\\Foo',
                ],
            ],
            'within-multiple-namespaces-with-braces' => [
                __DIR__ . '/../Fixture/Classy/WithinMultipleNamespaces/source.php',
                [
                    'Baz\\Bar\\Foo\\Bar',
                    'Baz\\Bar\\Foo\\Baz',
                    'Baz\\Bar\\Foo\\Foo',
                    'Foo\\Bar\\Baz\\Bar',
                    'Foo\\Bar\\Baz\\Baz',
                    'Foo\\Bar\\Baz\\Foo',
                ],
            ],
            'with-methods-named-after-keywords' => [
                __DIR__ . '/../Fixture/Classy/WithMethodsNamedAfterKeywords/source.php',
                [
                    'Foo\\Bar\\Baz\\Foo',
                ],
            ],
            /**
             * @see https://github.com/zendframework/zend-file/pull/41
             */
            'with-methods-named-after-keywords-and-return-type' => [
                __DIR__ . '/../Fixture/Classy/WithMethodsNamedAfterKeywordsAndReturnType/source.php',
                [
                    'Foo\\Bar\\Baz\\Foo',
                ],
            ],
            'without-namespace' => [
                __DIR__ . '/../Fixture/Classy/WithoutNamespace/source.php',
                [
                    'Bar',
                    'Baz',
                    'Foo',
                ],
            ],
            'without-namespace-and-multi-line-comments' => [
                __DIR__ . '/../Fixture/Classy/WithoutNamespaceAndMultiLineComments/source.php',
                [
                    'Bar',
                    'Baz',
                    'Foo',
                ],
            ],
            'without-namespace-and-shell-line-comments' => [
                __DIR__ . '/../Fixture/Classy/WithoutNamespaceAndShellStyleComments/source.php',
                [
                    'Bar',
                    'Baz',
                    'Foo',
                ],
            ],
            'without-namespace-and-single-line-comments' => [
                __DIR__ . '/../Fixture/Classy/WithoutNamespaceAndSingleLineComments/source.php',
                [
                    'Bar',
                    'Baz',
                    'Foo',
                ],
            ],
        ];
    }

    private function sourceTriggeringParseError(): string
    {
        return <<<'TXT'
<?php

final class MessedUp
{
TXT;
    }
}
