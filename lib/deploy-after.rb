before( "deploy", "git:submodule_tags" ) if git_enable_submodules
after( "deploy", "composer:run_composer" ) if git_enable_composer