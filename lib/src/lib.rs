#![cfg_attr(windows, feature(abi_vectorcall))]
use ext_php_rs::prelude::*;

pub mod task;

#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module
}
