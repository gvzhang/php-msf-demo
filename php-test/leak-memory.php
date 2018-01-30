<?php
class Foo {
function __construct()
{
$this->bar = new Bar($this);
}
}
 
class Bar {
function __construct($foo = null)
{
$this->foo = $foo;
}
}

while (true) {
$foo = new Foo();
unset($foo);
echo number_format(memory_get_usage()) . "\n";
}
