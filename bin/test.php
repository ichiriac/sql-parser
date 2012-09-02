<?php

require_once __DIR__ . '/../src/Parser.php';

$parser = new \beaba\storage\Parser();

print_r( 
    $parser->read(
        '
            SELECT 
                t.*, 
                o.id as o_id,
                max(o.order) as max_order,
                count(o.*) nb_o,
                concat(t.title, \' --> \', t.description) as desc
            FROM 
                my_table t
            INNER JOIN
                other_table o 
                ON o.fk_table = t.id
                AND o.criteria > 123
            OUTTER JOIN
                referenced_table rt
                ON rt.id = o.id
            WHERE
                t.other_criteria = \'some text\' AND
                t.date BETWEEN \'2011-01-01 23:59:59\' AND NOW()
                AND (
                    rt.sub_criteria = 1 OR
                    o.another_criteria <> 1
                ) AND
                o.id IN(1, 2, 3)
            GROUP BY t.id
            ORDER BY
                t.order ASC,
                t.date DESC
            LIMIT 0, 50
        '
    ) 
);

print_r( 
    $parser->read(
        '
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
                t.order ASC,
                t.date DESC
            LIMIT 0, 50
        '
    )
);

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

$param_parser = new ParamParser();
$request = $param_parser->read(
    'SELECT :primary FROM :table WHERE :primary IN (:values)'
);
$request->setParam( 'primary', 'id' );
print_r($request);