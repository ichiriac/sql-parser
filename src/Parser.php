<?php

namespace beaba\storage;
/**
 * This file is distributed under the MIT Open Source
 * License. See README.MD for details.
 * @author Ioan CHIRIAC
 * @link https://github.com/ichiriac/sql-parser
 */

/**
 * Defines a parser exception
 */
class ParseException extends \Exception
{
    
}

/**
 * The parser only supports CRUD requests
 */
class UnsuportedMethod extends \Exception
{
    
}

/**
 * The SQL storage parser
 */
class Parser
{

    /**
     * Reads the specified statement
     * @param string $statement
     * @return array
     */
    public function read($statement)
    {
        // split the statement into tokens
        $tokens = $this->tokenize($statement);
        $size = count($tokens);
        $offset = 0;
        $result = $this->analyze($tokens, $size, $offset);
        // parsing the result and analyse the request
        foreach ($result as $f => $prop) {
            switch ($f[0]) {
                case '-':
                    switch ($f[1]) {
                        case 's':
                            $result[$f] = $this->parseSelect($prop);
                            break;
                        default:
                            throw new UnsuportedMethod(
                                'Unable to handle : ' . $f
                            );
                    }
                    break;
                case 'f':
                    $result[$f] = $this->parseFrom($prop);
                    break;
                case 'j':
                    if (empty($result['j']))
                        $result['j'] = array();
                    unset($result[$f]);
                    $join = $this->parseJoin($prop);
                    if (empty($join['a'])) {
                        $result['j'][] = $join;
                    } else {
                        $result['j'][$join['a']] = $join;
                    }
                    break;
                case 'w':
                    $result[$f] = $this->parseCriteria($prop);
                    break;
                case 'o':
                    $result[$f] = $this->parseOrders($prop);
                    break;
                case 'g':
                    $result[$f] = $this->parseGroup($prop);
                    break;
                case 'l':
                    $result[$f] = $this->parseLimit($prop);
                    break;
            }
        }
        return $result;
    }

    /**
     * Parse the group by statement
     * @param array $props
     * @return array 
     */
    protected function parseGroup(array $props)
    {
        $result = array();
        if (strtolower($props[0]) === 'by') {
            array_shift($props);
        }
        $len = count($props);
        for ($i = 0; $i < $len; $i+=2) {
            $result[] = $props[$i];
            if (!empty($props[$i + 1]) && $props[$i + 1] !== ',') {
                throw new ParseException(
                    'Expect [,] to separe group by columns'
                );
            }
        }
        return $result;
    }

    /**
     * Parsing a list of orders statement
     * @param array $props
     * @return array
     */
    protected function parseOrders(array $props)
    {
        $result = array();
        if (strtolower($props[0]) === 'by') {
            array_shift($props);
        }
        $len = count($props);
        for ($i = 0; $i < $len; $i+=2) {
            $item = array(
                $props[$i]
            );
            if (!empty($props[$i + 1])) {
                $next = strtolower($props[$i + 1]);
                if ($next === ',') {
                    $item[] = true;
                } elseif ($next === 'asc' || $next === 'desc') {
                    $i++;
                    $item[] = $next === 'asc';
                } else {
                    throw new ParseException(
                        'Unexpected order token ' . $next
                    );
                }
                if (!empty($props[$i + 1]) && $props[$i + 1] !== ',') {
                    throw new ParseException(
                        'Expect [,] to separe order columns'
                    );
                }
            }
            $result[] = $item;
        }
        return $result;
    }

    /**
     * Parsing the limit statement
     * @param array $props
     * @return array
     */
    protected function parseLimit(array $props)
    {
        $len = count($props);
        if ($len === 1) {
            return array(
                0, $props[0]
            );
        } elseif ($len === 3) {
            return array(
                $props[0], $props[2]
            );
        } else {
            throw new ParseException(
                'Bad limit syntax'
            );
        }
    }

    /**
     * Parse list of join properties
     * @param array $props
     * @return array
     */
    protected function parseJoin(array $props)
    {
        $result = array(
            '$' => $props[0],
            't' => $props[1]
        );
        if (strtolower($props[2]) === 'as') {
            $result['a'] = $props[3];
            $offset = 4;
        } elseif (strtolower($props[2]) !== 'on') {
            $result['a'] = $props[2];
            $offset = 3;
        } else {
            $offset = 2;
        }
        if (strtolower($props[$offset]) !== 'on') {
            throw new ParseException(
                'Expect ON statement in join'
            );
        }
        $result['c'] = $this->parseCriteria($props, $offset + 1);
        return $result;
    }

    /**
     * Parsing a list of criterias
     * @param array $props
     */
    protected function parseCriteria(array $props, $offset = 0)
    {
        $result = array();
        $compare = array('<', '>', '=');
        $len = count($props);
        for (; $offset < $len; $offset += 4) {
            $criteria = array(
                'f' => $props[$offset]
            );
            if (is_array($props[$offset + 1])) {
                $offset--;
                $criteria['c'] = '';
            } else {
                $criteria['c'] = strtolower($props[$offset + 1]);
            }
            if (in_array($props[$offset + 2], $compare)) {
                $offset += 1;
                $criteria['c'] .= $props[$offset + 1];
            }
            if ($criteria['c'] === 'between') {
                $offset++;
                if (strtolower($props[$offset + 2]) !== 'and') {
                    throw new ParseException(
                        'Expect AND for the between statement'
                    );
                }
                $offset++;
                $props[$offset + 2] = array(
                    $props[$offset],
                    $props[$offset + 2]
                );
            } elseif ($criteria['c'] === 'is') {
                if (strtolower($props[$offset + 2]) === 'not') {
                    $offset++;
                    $criteria['c'] .= ' not';
                }
            }
            if (is_array($props[$offset + 2])) {
                $criteria['c'] .= strtolower($props[$offset + 2]['$']);
                $criteria['v'] = $props[$offset + 2]['?'];
            } else {
                $criteria['v'] = $props[$offset + 2];
            }

            $result[] = $criteria;
            if (!empty($props[$offset + 3])) {
                if (is_array($props[$offset + 3])) {
                    $result[] = strtolower($props[$offset + 3]['$']);
                    $result[] = $this->parseCriteria($props[$offset + 3]['?']);
                    $offset ++;
                    if ( !empty($props[$offset + 3]) ) {
                        $result[] = strtolower($props[$offset + 3]);
                    }
                } else {
                    $result[] = strtolower($props[$offset + 3]);
                }
            }
        }
        return $result;
    }

    /**
     * Parsing a select statement
     * @param array $props
     * @return array 
     */
    protected function parseSelect(array $props)
    {
        $result = array();
        $len = count($props);
        for ($i = 0; $i < $len; $i += 2) {
            $tok = $props[$i];
            if (!empty($props[$i + 1])) {
                $next = $props[$i + 1];
                if ($next === ',') {
                    $result[] = $tok;
                } elseif (strtolower($next) === 'as') {
                    // field as alias
                    $i += 2;
                    $result[$props[$i]] = $tok;
                } else {
                    // field alias
                    $i += 1;
                    $result[$next] = $tok;
                    if (!empty($props[$i + 1]) && $props[$i + 1] !== ',') {
                        throw new ParseException(
                            'Bad select syntax : expecting [,]'
                        );
                    }
                }
            } else {
                $result[] = $tok;
            }
        }
        return $result;
    }

    /**
     * Parsing a from statement
     * @param array $props 
     */
    protected function parseFrom(array $props)
    {
        $result = array();
        $len = count($props);
        for ($i = 0; $i < $len; $i += 2) {
            $tok = $props[$i];
            if (!empty($props[$i + 1])) {
                $next = $props[$i + 1];
                if ($next === ',') {
                    $result[] = $tok;
                } elseif (strtolower($next) === 'as') {
                    // field as alias
                    $i += 2;
                    $result[$props[$i]] = $tok;
                } else {
                    // field alias
                    $i += 1;
                    $result[$next] = $tok;
                    if (!empty($props[$i + 1]) && $props[$i + 1] !== ',') {
                        throw new ParseException(
                            'Bad from syntax : expecting [,]'
                        );
                    }
                }
            } else {
                $result[] = $tok;
            }
        }
        return $result;
    }

    /**
     * Cut the statement in tokens
     * @param string $statement
     * @return array
     */
    protected function tokenize($statement)
    {
        // tokenizer
        $ignore = array(' ', "\n", "\t");
        $separators = array(',', '=', '<', '>', '(', ')');
        $tokens = array();
        $statement = trim($statement);
        $len = strlen($statement);
        $offset = 0;
        $next = false;
        $textMode = null;
        for ($i = 0; $i < $len; $i++) {
            $char = $statement[$i];
            if (!$textMode) {
                if (in_array($char, $ignore)) {
                    $next = true;
                    continue;
                }
                if (in_array($char, $separators)) {
                    $tokens[++$offset] = $char;
                    $next = true;
                    continue;
                }
                if ($next) {
                    $offset++;
                    $next = false;
                }
                if (empty($tokens[$offset]))
                    $tokens[$offset] = '';
            } else {
                if ($char === '\\') {
                    $char .= $statement[++$i];
                }
            }
            if ($char === '\'' || $char === '\"') {
                if ($textMode === $char) {
                    $next = true;
                    $textMode = null;
                } else {
                    $textMode = $char;
                }
            }
            $tokens[$offset] .= $char;
        }
        return $tokens;
    }

    /**
     * Analyse the tokens structure and build a request tree
     * @param array $tokens
     * @param integer $size
     * @param integer $offset
     * @return array 
     */
    protected function analyze(array $tokens, $size, &$offset)
    {
        // reads the request type
        switch (strtolower($tokens[$offset])) {
            case 'select':
                $key = '-s';
                break;
            case 'update':
                $key = '-u';
                break;
            case 'delete':
                $key = '-d';
                break;
            case 'insert':
                $key = '-i';
                break;
            default:
                $key = '?';
        }
        if ($key[0] === '-')
            $offset++;
        // building a parsing tree
        $structure = array();
        $j = 0;
        for (; $offset < $size; $offset++) {
            $tok = $tokens[$offset];
            switch (strtolower($tok)) {
                case 'with':
                    $key = '+';
                    break;
                case 'from':
                    $key = 'f';
                    break;
                case 'inner':
                case 'outter':
                case 'left':
                case 'right':
                    if (strtolower($tokens[$offset + 1]) === 'join') {
                        $offset++;
                        $key = 'j' . ($j++);
                        $structure[$key] = array(strtolower($tok));
                    } else {
                        $structure[$key][] = $tok;
                    }
                    break;
                case 'join':
                    $key = 'j' . ($j++);
                    $structure[$key] = array('left');
                    break;
                case 'where':
                    $key = 'w';
                    break;
                case 'order':
                    $key = 'o';
                    break;
                case 'limit':
                    $key = 'l';
                    break;
                case 'group':
                    $key = 'g';
                    break;
                default:
                    if (empty($structure[$key]))
                        $structure[$key] = array();
                    if ($tok === '(') {
                        $tok = $this->analyze($tokens, $size, ++$offset);
                        $tok['$'] = array_pop($structure[$key]);
                        $structure[$key][] = $tok;
                        continue;
                    }
                    if ($tok === ')') {
                        return $structure;
                    }
                    $structure[$key][] = $this->addToken($tok);
            }
        }
        return $structure;
    }

    /**
     * Add a token
     * @param string $token
     * @return mixed
     */
    protected function addToken($token)
    {
        return $token;
    }

}
