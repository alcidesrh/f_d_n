import { ApolloClient } from "@apollo/client/core";

declare module "@vue/runtime-core" {
  interface ComponentCustomProperties {
    $apollo: ApolloClient<any>;
  }
}
