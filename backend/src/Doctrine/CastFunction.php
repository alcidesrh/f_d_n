<?php

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

class CastFunction extends FunctionNode {
  private $field;
  private $type;

  public function parse(Parser $parser): void {
    $parser->match(TokenType::T_IDENTIFIER);
    $parser->match(TokenType::T_OPEN_PARENTHESIS);
    $this->field = $parser->ArithmeticExpression();
    $parser->match(TokenType::T_AS);
    $parser->match(TokenType::T_IDENTIFIER);
    $this->type = $parser->getLexer()->token->value;
    $parser->match(TokenType::T_CLOSE_PARENTHESIS);
  }

  public function getSql(SqlWalker $sqlWalker): string {
    return sprintf('CAST(%s AS %s)', $this->field->dispatch($sqlWalker), $this->type);
  }
}
