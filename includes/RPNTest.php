<?php

/*
    Shunting Yard Algorithm
    Code by igorw
    https://gist.github.com/igorw/6902824
*/

echo "RPNTest";

$input = '1+2^3';

$operators = [
  '+' => ['precedence' => 0, 'associativity' => 'left'],
  '-' => ['precedence' => 0, 'associativity' => 'left'],
  '*' => ['precedence' => 1, 'associativity' => 'left'],
  '/' => ['precedence' => 1, 'associativity' => 'left'],
  '%' => ['precedence' => 1, 'associativity' => 'left'],
  '^' => ['precedence' => 2, 'associativity' => 'right'],
];

$tokens = tokenize($input);
$rpn = shunting_yard($tokens, $operators);
$result = execute($rpn);

var_dump($tokens);
var_dump($rpn);
var_dump($result);

function tokenize($input){ return str_split(str_replace(' ', '', $input)); }

function shunting_yard(array $tokens, array $operators)
{
    $stack = new \SplStack();
    $output = new \SplQueue();

    foreach ($tokens as $token) {
        if (is_numeric($token)) {
            $output->enqueue($token);
        } elseif (isset($operators[$token])) {
            $o1 = $token;
            while (has_operator($stack, $operators) && ($o2 = $stack->top()) && has_lower_precedence($o1, $o2, $operators)) {
                $output->enqueue($stack->pop());
            }
            $stack->push($o1);
        } elseif ('(' === $token) {
            $stack->push($token);
        } elseif (')' === $token) {
            while (count($stack) > 0 && '(' !== $stack->top()) {
                $output->enqueue($stack->pop());
            }

            if (count($stack) === 0) {
                throw new \InvalidArgumentException(sprintf('Mismatched parenthesis in input: %s', json_encode($tokens)));
            }

            // pop off '('
            $stack->pop();
        } else {
            throw new \InvalidArgumentException(sprintf('Invalid token: %s', $token));
        }
    }

    while (has_operator($stack, $operators)) {
        $output->enqueue($stack->pop());
    }

    if (count($stack) > 0) {
        throw new \InvalidArgumentException(sprintf('Mismatched parenthesis or misplaced number in input: %s', json_encode($tokens)));
    }

    return iterator_to_array($output);
}

function has_operator(\SplStack $stack, array $operators)
{
    return count($stack) > 0 && ($top = $stack->top()) && isset($operators[$top]);
}

function has_lower_precedence($o1, $o2, array $operators)
{
    $op1 = $operators[$o1];
    $op2 = $operators[$o2];
    return ('left' === $op1['associativity'] && $op1['precedence'] === $op2['precedence']) || $op1['precedence'] < $op2['precedence'];
}

function execute(array $ops)
{
    $stack = new \SplStack();

    foreach ($ops as $op) {
        if (is_numeric($op)) {
            $stack->push((float) $op);
            continue;
        }

        switch ($op) {
            case '+':
                $stack->push($stack->pop() + $stack->pop());
                break;
            case '-':
                $n = $stack->pop();
                $stack->push($stack->pop() - $n);
                break;
            case '*':
                $stack->push($stack->pop() * $stack->pop());
                break;
            case '/':
                $n = $stack->pop();
                $stack->push($stack->pop() / $n);
                break;
            case '%':
                $n = $stack->pop();
                $stack->push($stack->pop() % $n);
                break;
            case '^':
                $n = $stack->pop();
                $stack->push(pow($stack->pop(), $n));
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Invalid operation: %s', $op));
                break;
        }
    }

    return $stack->top();
}

/* This implementation does not implement composite functions,functions with variable number of arguments, and unary operators. */

//while there are tokens to be read:
//  read a token.
//  if the token is a number, then:
//      push it to the output queue.
//  else if the token is a function then:
//      push it onto the operator stack 
//  else if the token is an operator then:
//      while ((there is an operator at the top of the operator stack)
//            and ((the operator at the top of the operator stack has greater precedence)
//                or (the operator at the top of the operator stack has equal precedence and the token is left associative))
//            and (the operator at the top of the operator stack is not a left parenthesis)):
//          pop operators from the operator stack onto the output queue.
//      push it onto the operator stack.
//  else if the token is a left parenthesis (i.e. "("), then:
//      push it onto the operator stack.
//  else if the token is a right parenthesis (i.e. ")"), then:
//      while the operator at the top of the operator stack is not a left parenthesis:
//          pop the operator from the operator stack onto the output queue.
//      /* If the stack runs out without finding a left parenthesis, then there are mismatched parentheses. */
//      if there is a left parenthesis at the top of the operator stack, then:
//          pop the operator from the operator stack and discard it
///* After while loop, if operator stack not null, pop everything to output queue */
//if there are no more tokens to read then:
//  while there are still operator tokens on the stack:
//      /* If the operator token on the top of the stack is a parenthesis, then there are mismatched parentheses. */
//      pop the operator from the operator stack onto the output queue.
//exit.

echo "<hr />";