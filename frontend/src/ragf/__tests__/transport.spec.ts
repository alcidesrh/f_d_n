import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { GraphQLClient, RestClient } from "@/ragf/transport/client";
import { RagfGraphQLError, RagfTransportError } from "@/ragf/transport/errors";
import { createRagf } from "@/ragf";
import { resolveConfig } from "@/ragf/config";

function jsonResponse(body: unknown, status = 200, headers: Record<string, string> = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json", ...headers },
  });
}

describe("GraphQLClient", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("posts the document and variables and unwraps data", async () => {
    vi.mocked(fetch).mockResolvedValue(jsonResponse({ data: { buses: [{ id: "1" }] } }));

    const client = new GraphQLClient({ endpoint: "http://backend/graphql" });
    const data = await client.query<{ buses: { id: string }[] }>(
      "query ($page: Int!) { buses(currentPage: $page) { id } }",
      { page: 1 },
    );

    expect(data.buses[0]?.id).toBe("1");
    const [url, init] = vi.mocked(fetch).mock.calls[0]!;
    const headers = (init && init.headers) as Record<string, string> | undefined;
    expect(String(url)).toBe("http://backend/graphql");
    expect(init?.method).toBe("POST");
    expect(JSON.parse(String(init?.body))).toEqual({
      query: "query ($page: Int!) { buses(currentPage: $page) { id } }",
      variables: { page: 1 },
    });
    expect(headers?.["Content-Type"]).toBe("application/json");
  });

  it("attaches the bearer token once set", async () => {
    vi.mocked(fetch).mockResolvedValue(jsonResponse({ data: { __typename: "Query" } }));

    const client = new GraphQLClient({ endpoint: "http://backend/graphql" });
    client.setAuthToken("tok-123");
    await client.request("{ __typename }");

    const init = vi.mocked(fetch).mock.calls[0]![1];
    const headers = (init && init.headers) as Record<string, string> | undefined;
    expect(headers?.["Authorization"]).toBe("Bearer tok-123");
  });

  it("throws RagfGraphQLError with readable messages on GraphQL errors", async () => {
    const payload = {
      data: null,
      errors: [
        { message: 'Field "nope" does not exist', path: ["buses", "nope"] },
        { message: "Invalid input" },
      ],
    };
    vi.mocked(fetch).mockImplementation(() => Promise.resolve(jsonResponse(payload)));

    const client = new GraphQLClient({ endpoint: "http://backend/graphql" });
    await expect(client.request("{ buses { nope } }")).rejects.toThrow(RagfGraphQLError);
    await expect(client.request("{ buses { nope } }")).rejects.toMatchObject({
      messages: 'Field "nope" does not exist @ buses.nope; Invalid input',
    });
  });

  it("throws RagfTransportError on HTTP failures with the status", async () => {
    vi.mocked(fetch).mockResolvedValue(jsonResponse({ error: "boom" }, 502, {}));

    const client = new GraphQLClient({ endpoint: "http://backend/graphql" });
    await expect(client.request("{ __typename }")).rejects.toThrow(RagfTransportError);
    await expect(client.request("{ __typename }")).rejects.toMatchObject({ status: 502 });
  });

  it("throws RagfTransportError when the network fails", async () => {
    vi.mocked(fetch).mockRejectedValue(new TypeError("Failed to fetch"));

    const client = new GraphQLClient({ endpoint: "http://backend/graphql" });
    await expect(client.request("{ __typename }")).rejects.toThrow(
      "Network error reaching http://backend/graphql",
    );
  });

  it("throws RagfTransportError on non-JSON responses", async () => {
    vi.mocked(fetch).mockResolvedValue(
      new Response("<html>proxy error</html>", {
        status: 200,
        headers: { "Content-Type": "text/html" },
      }),
    );

    const client = new GraphQLClient({ endpoint: "http://backend/graphql" });
    await expect(client.request("{ __typename }")).rejects.toThrow(RagfTransportError);
  });
});

describe("RestClient", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("GETs JSON relative to the base url", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse({ "@id": "/api/entity_configurations/26" }),
    );

    const rest = new RestClient({ baseUrl: "http://backend/api" });
    const body = await rest.getJson<{ "@id": string }>("/entity_configurations?entityClass=Bus");

    expect(body["@id"]).toBe("/api/entity_configurations/26");
    const [url, init] = vi.mocked(fetch).mock.calls[0]!;
    expect(String(url)).toBe("http://backend/api/entity_configurations?entityClass=Bus");
    expect(init?.method).toBe("GET");
  });
});

describe("resolveConfig", () => {
  it("derives the REST base from the GraphQL entrypoint origin", () => {
    const config = resolveConfig({ graphqlEndpoint: "http://localhost/graphql" });
    expect(config.graphqlEndpoint).toBe("http://localhost/graphql");
    expect(config.restEndpoint).toBe("http://localhost/api");
  });

  it("strips trailing slashes and honors explicit overrides", () => {
    const config = resolveConfig({
      graphqlEndpoint: "https://api.example.com/graphql/",
      restEndpoint: "https://api.example.com/rest/",
      mercureUrl: "https://hub.example.com/",
    });
    expect(config).toEqual({
      graphqlEndpoint: "https://api.example.com/graphql",
      restEndpoint: "https://api.example.com/rest",
      mercureUrl: "https://hub.example.com",
    });
  });
});

describe("createRagf", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("pings the entrypoint and reports connectivity", async () => {
    vi.mocked(fetch).mockResolvedValue(jsonResponse({ data: { __typename: "Query" } }));

    const ragf = createRagf({ graphqlEndpoint: "http://localhost/graphql" });
    await expect(ragf.ping()).resolves.toBe(true);

    const [url, init] = vi.mocked(fetch).mock.calls[0]!;
    expect(String(url)).toBe("http://localhost/graphql");
    expect(JSON.parse(String(init?.body)).query).toContain("__typename");
  });
});