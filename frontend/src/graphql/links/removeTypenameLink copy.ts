import { ApolloLink, Observable } from "@apollo/client";

const removeTypename = (obj: any): any => {
  if (Array.isArray(obj)) {
    return obj.map(removeTypename);
  }
  if (obj !== null && typeof obj === "object") {
    const { __typename, ...rest } = obj;
    return Object.keys(rest).reduce((acc: any, key) => {
      acc[key] = removeTypename(rest[key]);
      return acc;
    }, {});
  }
  return obj;
};

export const removeTypenameLink = new ApolloLink((operation, forward) => {
  return new Observable((observer) => {
    const subscription = forward(operation).subscribe({
      next: (response) => {
        if (response.data) {
          response.data = removeTypename(response.data);
        }
        observer.next(response);
      },
      error: (error) => observer.error(error),
      complete: () => observer.complete(),
    });

    return () => subscription.unsubscribe();
  });
});
