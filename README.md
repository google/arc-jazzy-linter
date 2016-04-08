# jazzy-linter

Use [jazzy](http://github.com/realm/jazzy) to lint your Objective-C and Swift documentation
with [Phabricator](http://phabricator.org)'s `arc` command line tool.

## Features

Identify missing documentation for Objective-C and Swift APIs.

    ~ $ arc lint
    >>> Lint for SomeClass.h:
    
       Warning (JAZZY1) Missing documentation
        SomeEnumDefault is missing documentation.
        Please use `/** */` blocks to document APIs.
        
                  25  *
                  26  */
                  27 typedef NS_ENUM(NSInteger, SomeEnum) {
        >>>       28   SomeEnumDefault,
                  29   SomeEnumCustom
                  30 };
                  31 

## Installation

Jazzy 0.6.0 or higher is required.

    gem install jazzy

Verify your version by running:

    jazzy -v

### Project-specific installation

You can add this repository as a git submodule. Add a path to the submodule in your `.arcconfig`
like so:

```json
{
  "load": ["path/to/jazzy-linter"]
}
```

### Global installation

`arcanist` can load modules from an absolute path. But it also searches for modules in a directory
up one level from itself.

You can clone this repository to the same directory where `arcanist` and `libphutil` are located.
In the end it will look like this:

```sh
arcanist/
jazzy-linter/
libphutil/
```

Your `.arcconfig` would look like

```json
{
  "load": ["jazzy-linter"]
}
```

## Setup

To use the linter you must register it in your `.arclint` file.

```json
{
  "linters": {
    "jazzy": {
      "type": "jazzy",
      "include": "(\\.(h|swift)$)"
    }
  }
}
```

You must also provide a `.jazzy.yaml` file somewhere in your repository. If your project consists
of multiple parts then you may wish to create multiple `.jazzy.yaml` files. Place the `.jazzy.yaml`
either in the same directory as your source or in a parent directory.

```
myProject/
  .jazzy.yaml
  src/
    SomeClass.h
    SomeClass.m
```

Run `jazzy --help config` for help with the relevant config values.

Example `.jazzy.yaml` for an Objective-C library:

```
module_name: Module
umbrella_header: src/Module.h
objc: true
sdk: iphonesimulator
```

## License

Licensed under the Apache 2.0 license. See LICENSE for details.
