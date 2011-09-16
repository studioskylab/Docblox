<?php

/**
 * Sphinx transformation writer; generates Sphinx PHP Domain-compatible rst
 * files for incorporation into a Sphinx documentation project
 *
 * @category DocBlox
 * @package  Writers
 * @author   Jaik Dean <jaik@studioskylab.com>
 * @license  http://www.opensource.org/licenses/mit-license.php MIT
 */
class DocBlox_Transformer_Writer_Sphinx extends DocBlox_Transformer_Writer_Abstract
{

	/**
	 * XPath query instance
	 *
	 * @var DOMXPath
	 **/
	protected $xpath;

	/**
	 * undocumented class variable
	 *
	 * @var array
	 **/
	protected $packages;

	/**
	 * undocumented class variable
	 *
	 * @var string
	 **/
	protected $transformation;


	/**
	 * undocumented function
	 *
	 * @param DOMDocument                        $structure      XML source.
	 * @param DocBlox_Transformer_Transformation $transformation Transformation.
	 * @throws Exception
	 * @return void
	 * @author Jaik Dean
	 **/
	public function transform(DOMDocument $structure, DocBlox_Transformer_Transformation $transformation)
	{
		$this->xpath          = new DOMXPath($structure);
		$this->packages       = array();
		$this->transformation = $transformation;

		// process the interfaces
		$interfaces = $this->xpath->query('//interface[full_name]');
		foreach ($interfaces as $interface) {
			$this->formatInterface($interface);
		}

		// process the classes
		$classes = $this->xpath->query('//class[full_name]');
		foreach ($classes as $class) {
			$this->formatClass($class);
		}

		/* @todo
		foreach ($file->constant as $constant) {
			$node = $output->addChild('node');
			$node->value = (string) $constant->name;
			$node->id = $file['generated-path'] . '#::' . $node->value;
			$node->type = 'constant';
		}*/

		/* todo
		foreach ($file->function as $function) {
			$node = $output->addChild('node');
			$node->value = (string) $function->name . '()';
			$node->id = $file['generated-path'] . '#::' . $node->value;
			$node->type = 'function';
		}*/

		// generate TOC
		// @todo Create smaller TOC for each package/subpackage instead of one "super-TOC"
		$toc  = "API Documentation\n";
		$toc .= "-----------------\n\n";
		$toc .= ".. toctree::\n";
		ksort($this->packages);

		foreach ($this->packages as $package => $subpackages) {
			foreach ($subpackages as $subpackage => $elements) {
				foreach ($elements as $element => $file) {
					$toc .= "\n\t$package/$subpackage/$element";
				}
			}
		}

		$this->file_force_contents($transformation->getTransformer()->getTarget() . DIRECTORY_SEPARATOR . 'index.rst', $toc);
	}


	/**
	 * undocumented function
	 *
	 * @param DOMElement $class
	 * @return void
	 * @author Jaik Dean
	 **/
	protected function formatClass($class)
	{
		$this->formatObject($class, 'class');
	}


	/**
	 * undocumented function
	 *
	 * @param DOMElement $interface
	 * @return void
	 * @author Jaik Dean
	 **/
	protected function formatInterface($interface)
	{
		$this->formatObject($interface, 'interface');
	}


	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Jaik Dean
	 **/
	protected function formatObject($object, $type = 'class')
	{
		$package          = $this->xpath->query('docblock/tag[@name="package"]', $object->parentNode);
		$package          = ($package->length ? (string) $package->item(0)->getAttribute('description') : 'NONE');
		$subpackage       = $this->xpath->query('docblock/tag[@name="subpackage"]', $object->parentNode);
		$subpackage       = ($subpackage->length ? (string) $subpackage->item(0)->getAttribute('description') : 'NONE');
		$name             = $this->xpath->evaluate('string(name[1])', $object);
		$description      = $this->xpath->evaluate('string(docblock/description[1])', $object);
		$full_description = $this->xpath->evaluate('string(docblock/full_description[1])', $object);

		// generate the file name
		$filename = $package . DIRECTORY_SEPARATOR . $subpackage . DIRECTORY_SEPARATOR . $name . '.rst';

		// register the file
		$this->packages[$package][$subpackage][$name] = $filename;

		// build the file contents
		$contents  = $name . "\n";
		$contents .= str_repeat('-', mb_strlen($name)) . "\n\n";
		$contents .= ".. php:{$type}:: {$name}\n\n";

		if ($description)      $contents .= "\t{$description}\n\n";
		if ($full_description) $contents .= "\t{$full_description}\n\n";

		foreach ($this->xpath->query('constant', $object) as $constant) {
			$contents .= $this->formatConstant($constant);
		}

		foreach ($this->xpath->query('property', $object) as $property) {
			$contents .= $this->formatProperty($property);
		}

		foreach ($this->xpath->query('method', $object) as $method) {
			$contents .= $this->formatMethod($method);
		}

		// output the file
		$this->file_force_contents($this->transformation->getTransformer()->getTarget() . DIRECTORY_SEPARATOR . $filename, $contents);
	}


	/**
	 * Create the Sphinx PHP Domain compatibile reStructuredText for the given
	 * constant
	 *
	 * @param DOMElement $constant
	 * @return string
	 * @author Jaik Dean
	 **/
	protected function formatConstant($constant)
	{
		$name  = $this->xpath->evaluate('string(name[1])', $constant);
		$value = $this->xpath->evaluate('string(value[1])', $constant);
		return "\t.. php:const:: {$name}\n\n\t\t{$value}\n";
	}


	/**
	 * Create the Sphinx PHP Domain compatibile reStructuredText for the given
	 * property
	 *
	 * @param DOMElement $property
	 * @return string
	 * @author Jaik Dean
	 **/
	protected function formatProperty($property)
	{
		$name  = $this->xpath->evaluate('string(name[1])', $property);
		$value = $this->xpath->evaluate('string(value[1])', $property);
		return "\t.. php:attr:: {$name}\n\n\t\t{$value}\n";
	}


	/**
	 * Create the Sphinx PHP Domain compatibile reStructuredText for the given
	 * method
	 *
	 * @param DOMElement $method
	 * @return string
	 * @author Jaik Dean
	 **/
	protected function formatMethod($method)
	{
		// build the method name
		$args  = '';
		$first = true;
		foreach ($method->getElementsByTagName('argument') as $argument) {
			$default = $this->xpath->evaluate('string(default[1])', $argument);

			if (!$first) $args .= ' ';
			if (mb_strlen($default)) $args .= '[';
			if (!$first) $args .= ', ';
			$args .= $this->xpath->evaluate('string(name[1])', $argument);
			if (mb_strlen($default)) $args .= " = {$default}]";
			$first = false;
		}

		$method_name      = $this->xpath->evaluate('string(name[1])', $method) . "($args)";
		$description      = str_replace(array("\n","\r"), ' ', $this->xpath->evaluate('string(docblock/description[1])', $method));
		$full_description = str_replace(array("\n","\r"), ' ', $this->xpath->evaluate('string(docblock/full_description[1])', $method));

		if ($method->getAttribute('static') == 'true') {
			$contents = "\t.. php:staticmethod:: {$method_name}\n\n";
		} else {
			$contents = "\t.. php:method:: {$method_name}\n\n";
		}

		if ($description)      $contents .= "\t\t" . $description . "\n\n";
		if ($full_description) $contents .= "\t\t" . $full_description . "\n\n";

		foreach ($method->getElementsByTagName('argument') as $argument) {
			$contents .= $this->formatArgument($argument);
		}

		$return = $this->xpath->query('docblock/tag[@name=return]', $method);
		if ($return->length) {
			$contents .= "\t\t:returns: {$return->item(0)->getAttribute('description')}\n";
			$contents .= "\t\t:rtype: {$return->item(0)->getAttribute('type')}\n\n";
		}

		return "$contents\n\n";
	}


	/**
	 * Create the Sphinx PHP Domain compatibile reStructuredText for the given
	 * function/method argument
	 *
	 * @param DOMElement $argument
	 * @return string
	 * @author Jaik Dean
	 **/
	protected function formatArgument($argument)
	{
		$type        = $this->xpath->evaluate('string(type[1])', $argument);
		$name        = $this->xpath->evaluate('string(name[1])', $argument);
		$description = $this->xpath->evaluate('string(description[1])', $argument);

		return "\t\t:param {$type} {$name}: {$description}\n";
	}


	/**
	 * Write the given contents in a file, creating any directories in the path
	 * as necessary.
	 *
	 * @param string $path
	 * @param string $contents
	 * @return void
	 * @author Jaik Dean
	 **/
	protected function file_force_contents($path, $contents)
	{
		$parts = explode('/', $path);
		$file  = array_pop($parts);
		$dir   = '';
		foreach ($parts as $part) {
		    if (!is_dir($dir .= "/$part")) {
				mkdir($dir);
			}
		}
		file_put_contents("$dir/$file", $contents);
	}

}