# sql-parser

This project contains a lightweight SQL parser written in PHP.

This parser enables you to analyze SQL requests and build your own
syntax helpers - very usefull for an ORM project.

## Why

* Doctrine HQL engine : 3100 LOC (+lexer)
* php-sql-parser (from google code) : 1600 LOC
* and this project : 500 LOC

## Sample

```
            SELECT 
                t.*, 
                o.id as o_id,
                max(o.order) as max_order,
                count(o.*) as nb_o
            FROM 
                my_table t
            INNER JOIN
                other_table o 
                ON o.fk_table = t.id
                AND o.criteria > 123
            WHERE
                t.other_criteria = \'some text\'
            GROUP BY t.id
            ORDER BY
                t.order ASC
                t.date DESC
            LIMIT 0, 50
```

And the result :

```
Array
(
    [-s] => Array
        (
            [0] => t.*
            [o_id] => o.id
            [max_order] => Array
                (
                    [?] => Array
                        (
                            [0] => o.order
                        )

                    [$] => max
                )

            [nb_o] => Array
                (
                    [?] => Array
                        (
                            [0] => o.*
                        )

                    [$] => count
                )

        )

    [f] => Array
        (
            [t] => my_table
        )

    [w] => Array
        (
            [0] => Array
                (
                    [f] => t.other_criteria
                    [c] => =
                    [v] => 'some text'
                )

        )

    [g] => Array
        (
            [0] => t.id
        )
    [o] => Array
        (
            [0] => Array
                (
                    [0] => t.order
                    [1] => 1
                )

            [1] => Array
                (
                    [0] => t.date
                    [1] =>
                )

        )

    [l] => Array
        (
            [0] => 0
            [1] => 50
        )

    [j] => Array
        (
            [o] => Array
                (
                    [$] => inner
                    [t] => other_table
                    [a] => o
                    [c] => Array
                        (
                            [0] => Array
                                (
                                    [f] => o.fk_table
                                    [c] => =
                                    [v] => t.id
                                )

                            [1] => and
                            [2] => Array
                                (
                                    [f] => o.criteria
                                    [c] => >
                                    [v] => 123
                                )

                        )

                )

        )

)
```

## Extending the lib

```
<?php
/**
 * Defines a parser that handles named parameters
 */
class ParamParser extends \beaba\storage\Parser {
    protected $params = array();
    
    // Injecting the request object
    public function read($statement)
    {
        $this->params = array();
        return new Request(
            parent::read($statement),
            $this->params
        );
    }
    
    // lazy load any parameter
    protected function getParam( $name ) {
        if ( !isset($this->params[$name])) {
            $this->params[$name] = new Param($name);
        }
        return $this->params[$name];
    }
    
    // intercept parameters from token parsing 
    protected function addToken($token)
    {
        if ( $token[0] === ':' ) {
            return $this->getParam( substr($token, 1) );
        } else {
            return parent::addToken($token);
        }
    }
}

// defines a simple parameter class
class Param {
    protected $name;
    protected $value;
    public function __construct($name) {
        $this->name = $name;
    }
    public function setValue( $value ) {
        $this->value = $value;
    }
}

// defines a simple request class
class Request {
    protected $request;
    protected $params;
    public function __construct( $request, $params ) {
        $this->request = $request;
        $this->params = $params;
    }
    public function setParam( $name, $value ) {
        if ( !isset($this->params[ $name ]) ) {
            throw new \OutOfBoundsException(
                'Undefined parameter : ' . $name
            );
        }
        $this->params[ $name ]->setValue( $value );
        return $this;
    }
}
```

## MIT License

Copyright (C) <2012> <PHP Hacks Team : http://coderwall.com/team/php-hacks>

Permission is hereby granted, free of charge, to any person obtaining a copy of 
this software and associated documentation files (the "Software"), to deal in 
the Software without restriction, including without limitation the rights to 
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 the Software, and to permit persons to whom the Software is furnished to do so, 
subject to the following conditions :

The above copyright notice and this permission notice shall be included in all 
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR 
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS 
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR 
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER 
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN 
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.