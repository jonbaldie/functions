**Functions** is a PHP skeleton project built upon a foundation of pure functions. It's a fun personal project of mine but it is unit tested and stable, if you'd like to give it a go.

Just run this to get started:

```
composer create-project jonbaldie/functions my-project
```

Then to generate your front-end assets:

```
yarn install && yarn encore dev
```

Wait, what's a "pure function"?

Pure functions map variables from an input and produce an output, with no observable side-effects. They're easily testable, can be moved anywhere, and used in any context with no unexpected effects. This concept comes from functional programming languages like Clojure, Scala, or Haskell.

Functional programming - an approach that holds pure functions at its core - is growing in popularity. Developers are challenging the utility of traditional methods that run the risk of producing code that's difficult to debug. What's simpler than a pure function that does nothing more than map an input to an output?