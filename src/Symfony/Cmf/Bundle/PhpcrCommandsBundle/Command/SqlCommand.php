<?php

/*
 * This file is part of the Symfony/Cmf/PhpcrCommandsBundle
 *
 * (c) Daniel Barsotti <daniel.barsotti@liip.ch>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Cmf\Bundle\PhpcrCommandsBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Jackalope\Node;
use Symfony\Cmf\Bundle\PhpcrCommandsBundle\Helper\NodeHelper;

use Symfony\Cmf\Bundle\PhpcrCommandsBundle\Helper\TreeWalker;
use Symfony\Cmf\Bundle\PhpcrCommandsBundle\Helper\TreeDumper\ConsoleDumperNodeVisitor;
use Symfony\Cmf\Bundle\PhpcrCommandsBundle\Helper\TreeDumper\ConsoleDumperPropertyVisitor;
use Symfony\Cmf\Bundle\PhpcrCommandsBundle\Helper\TreeDumper\SystemNodeFilter;

class SqlCommand extends PhpcrCommand
{
    /**
     * @var boolean
     */
    protected $dump_sys;

    /**
     * @var boolean
     */
    protected $dump_props;


    /**
     * Configures the current command.
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('phpcr:sql')
            ->addArgument('sql', InputArgument::REQUIRED, 'JCR SQL statement to execute')
            ->setDescription('Execute a JCR SQL2 statement')
            ->setHelp("The <info>phpcr:sql</info> command executes a JCR SQL2 statement on the content repository");
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return integer 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sql = $input->getArgument('sql');

        $session = $this->node_helper->getSession();
        $transport = $session->getTransport();
        $xml = $transport->querySQL($sql);

        $output->writeln($this->prettyXML($xml));

        return 0;
    }

    /**
     * Pretty an XML string typically returned from DOMDocument->saveXML()
     *
     * Ignores ?xml !DOCTYPE !-- tags (adjust regular expressions and pad/indent logic to change this)
     *
     * @param   string $xml the xml text to format
     * @param   boolean $debug set to get debug-prints of RegExp matches
     * @returns string formatted XML
     * @copyright TJ
     * @link kml.tjworld.net
    */
    function prettyXML($xml, $debug=false) {
      // add marker linefeeds to aid the pretty-tokeniser
      // adds a linefeed between all tag-end boundaries
      $xml = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $xml);

      // now pretty it up (indent the tags)
      $tok = strtok($xml, "\n");
      $formatted = ''; // holds pretty version as it is built
      $pad = 0; // initial indent
      $matches = array(); // returns from preg_matches()

      /* pre- and post- adjustments to the padding indent are made, so changes can be applied to
       * the current line or subsequent lines, or both
      */
      while($tok !== false) { // scan each line and adjust indent based on opening/closing tags

        // test for the various tag states
        if (preg_match('/.+<\/\w[^>]*>$/', $tok, $matches)) { // open and closing tags on same line
          if($debug) echo " =$tok= ";
          $indent=0; // no change
        }
        else if (preg_match('/^<\/\w/', $tok, $matches)) { // closing tag
          if($debug) echo " -$tok- ";
          $pad--; //  outdent now
        }
        else if (preg_match('/^<\w[^>]*[^\/]>.*$/', $tok, $matches)) { // opening tag
          if($debug) echo " +$tok+ ";
          $indent=1; // don't pad this one, only subsequent tags
        }
        else {
          if($debug) echo " !$tok! ";
          $indent = 0; // no indentation needed
        }

        // pad the line with the required number of leading spaces
        $prettyLine = str_pad($tok, strlen($tok)+$pad, ' ', STR_PAD_LEFT);
        $formatted .= $prettyLine . "\n"; // add to the cumulative result, with linefeed
        $tok = strtok("\n"); // get the next token
        $pad += $indent; // update the pad size for subsequent lines
      }
      return $formatted; // pretty format
    }

}