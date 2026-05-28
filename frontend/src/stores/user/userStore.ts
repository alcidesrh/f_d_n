import type { User } from "@/types/user";
import { defineStore } from "pinia";
export const useUserStore = defineStore(
  "userStore",
  () => {
    const store = createStore<User>("User");

    store.entity.value.excludeFormFields = [
      "password",
      "password_confirm",
      "userIdentifier",
      "validTokenStrings",
      "legacyId",
      "fullName",
      "roles",
    ];
    function submit(data) {
      // const { onDone, loading } =
      return apollo
        .mutate({
          operation: entity.value.getMutationOperation(),
          variables: Entity.prepareVariables(data),
          fields: entity.value.getMutationFields(),
        })
        .then(({ data }) => {
          entity.value.item = data[entity.value.getMutationOperation()].user;
        });
      // .catch((e) => {
      //   console.log("Error------> " + e);
      // });
    }

    return {
      ...store,
      submit,
    };
  },
  // persist,
);
