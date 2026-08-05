import { describe, expect, it } from "vitest";
import { createRagf } from "@/ragf";
import { RagfGraphQLError } from "@/ragf/transport/errors";

const liveEnabled = process.env.LIVE_BACKEND === "1";

describe("live backend smoke (M1)", { skip: !liveEnabled }, () => {
  it("pings the real GraphQL entrypoint", async () => {
    const ragf = createRagf();
    await expect(ragf.ping()).resolves.toBe(true);
  });

  it("queries real schema data for the Bus resource", async () => {
    const ragf = createRagf();
    const result = await ragf.graphql.query<{
      buses: { collection: Array<{ id: string }>; paginationInfo: { totalCount: number } };
    }>("query { buses(itemsPerPage: 2) { collection { id } paginationInfo { totalCount } } }");
    expect(Array.isArray(result.buses.collection)).toBe(true);
    expect(result.buses.paginationInfo.totalCount).toBeGreaterThan(0);
  });

  it("loads the real EntityConfiguration metadata over REST", async () => {
    const ragf = createRagf();
    const body = await ragf.rest.getJson<{ member: unknown[] }>(
      "/entity_configurations?entityClass=Bus",
    );
    expect(Array.isArray(body.member)).toBe(true);
    expect(body.member.length).toBeGreaterThan(0);
  });

  it("surfaces GraphQL errors for a malformed document", async () => {
    const ragf = createRagf();
    await expect(ragf.graphql.query("{ nope }")).rejects.toThrow(RagfGraphQLError);
  });
});