<?php

namespace App\Security;

use Casbin\Enforcer;

class ABAC {

  public function __construct(private ?Enforcer $enforce) {

    $this->enforce = new Enforcer("../vendor/casbin/casbin/examples/abac_model.conf");
  }

  public function test() {
    $sub = "alice"; // the user that wants to access a resource.
    $obj = (object)['Owner' => 'alice']; // the resource that is going to be accessed.
    $act = "read"; // the operation that the user performs on the resource.

    if ($this->enforce->enforce($sub, $obj, $act) === true) {
      return "access";
    } else {
      return  "deny";

      // deny the request, show an error
    }
  }
}
