#![cfg_attr(windows, feature(abi_vectorcall))]
use ext_php_rs::{prelude::*, types::ZendClassObject};

#[php_class]
pub struct Task {
    #[prop]
    name: str,
    #[prop]
    expression: str,
    #[prop]
    background: bool,
}

#[php_impl]
impl Task {

}
