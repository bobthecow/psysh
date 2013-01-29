<?php

namespace Psy\Test\CodeCleaner;

use PHPParser_Node_Expr_New as NewExpression;
use PHPParser_Node_Name as Name;
use PHPParser_Node_Name_FullyQualified as FullyQualifiedName;
use PHPParser_Node_Stmt_Class as ClassStatement;
use PHPParser_Node_Stmt_Interface as InterfaceStatement;
use PHPParser_Node_Stmt_Trait as TraitStatement;
use PHPParser_Node_Stmt_Namespace as NamespaceStatement;
use Psy\CodeCleaner\ValidClassNamePass;

class ValidClassNamePassTest extends \PHPUnit_Framework_TestCase
{
    private $pass;

    public function setUp()
    {
        $this->pass = new ValidClassNamePass;
    }

    /**
     * @dataProvider getInvalid
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessInvalid($stmts)
    {
        $this->pass->process($stmts);
    }

    public function getInvalid()
    {
        // class declarations
        return array(
            // core class
            array(array(
                new ClassStatement('StdClass'),
            )),

            // capitalization
            array(array(
                new ClassStatement('stdClass'),
            )),

            // collisions with interfaces and traits
            array(array(
                new InterfaceStatement('stdClass'),
            )),
            array(array(
                new TraitStatement('stdClass'),
            )),

            // collisions inside the same code snippet
            array(array(
                new ClassStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
                new ClassStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
            )),
            array(array(
                new ClassStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
                new TraitStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
            )),
            array(array(
                new TraitStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
                new ClassStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
            )),
            array(array(
                new TraitStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
                new InterfaceStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
            )),
            array(array(
                new InterfaceStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
                new TraitStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
            )),
            array(array(
                new InterfaceStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
                new ClassStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
            )),
            array(array(
                new ClassStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
                new InterfaceStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Alpha'),
            )),

            // namespaced collisions
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner'), array(
                    new ClassStatement('ValidClassNamePassTest'),
                )),
            )),
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidClassNamePass'), array(
                    new ClassStatement('Beta'),
                )),
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidClassNamePass'), array(
                    new ClassStatement('Beta'),
                )),
            )),

            // extends and implements
            array(array(
                new ClassStatement('ValidClassNamePassTest', array(
                    'extends' => new Name('NotAClass'),
                )),
            )),
            array(array(
                new ClassStatement('ValidClassNamePassTest', array(
                    'extends' => new Name('ArrayAccess'),
                )),
            )),
            array(array(
                new ClassStatement('ValidClassNamePassTest', array(
                    'implements' => array(new Name('StdClass')),
                )),
            )),
            array(array(
                new ClassStatement('ValidClassNamePassTest', array(
                    'implements' => array(new Name('ArrayAccess'), new Name('StdClass')),
                )),
            )),
            array(array(
                new InterfaceStatement('ValidClassNamePassTest', array(
                    'extends' => array(new Name('StdClass')),
                )),
            )),
            array(array(
                new InterfaceStatement('ValidClassNamePassTest', array(
                    'extends' => array(new Name('ArrayAccess'), new Name('StdClass')),
                )),
            )),

            // class instantiations
            array(array(
                new NewExpression(new Name('Psy_Test_CodeCleaner_ValidClassNamePass_Gamma')),
            )),
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidClassNamePass'), array(
                    new NewExpression(new Name('Psy_Test_CodeCleaner_ValidClassNamePass_Delta')),
                )),
            )),
        );
    }

   /**
    * @dataProvider getValid
    */
   public function testProcessValid($stmts)
   {
       $this->pass->process($stmts);
   }

   public function getValid()
   {
        return array(
            // class declarations
            array(array(
                new ClassStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Epsilon'),
            )),
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidClassNamePass'), array(
                    new ClassStatement('Zeta'),
                )),
            )),
            array(array(
                new ClassStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Eta'),
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidClassNamePass'), array(
                    new ClassStatement('Psy_Test_CodeCleaner_ValidClassNamePass_Eta'),
                )),
            )),
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidClassNamePass'), array(
                    new ClassStatement('StdClass'),
                )),
            )),

            // class instantiations
            array(array(
                new NewExpression(new Name('StdClass')),
            )),
            array(array(
                new NewExpression(new Name('stdClass')),
            )),
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidClassNamePass'), array(
                    new ClassStatement('Theta'),
                )),
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidClassNamePass'), array(
                    new NewExpression(new Name('Theta')),
                )),
            )),
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidClassNamePass'), array(
                    new ClassStatement('Iota'),
                    new NewExpression(new Name('Iota')),
                )),
            )),
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidClassNamePass'), array(
                    new ClassStatement('Kappa'),
                )),
                new NewExpression(new FullyQualifiedName('Psy\Test\CodeCleaner\ValidClassNamePass\Kappa')),
            )),
       );
   }
}
