import { LiveIntrospectionSource } from './src/features/graphql-orm/core/src/introspection/introspection-source';
import { SchemaRegistry } from './src/features/graphql-orm/core/src/schema/schema-registry';
import { FetchTransport } from './src/features/graphql-orm/core/src/transport/graphql-transport';
import { createRepository } from './src/features/graphql-orm/core/src/repository/create-repository';

async function main() {
  console.log('--- GraphQL-ORM Smoke Test ---');
  console.log('1. Loading introspection schema from http://localhost/graphql...');

  const source = new LiveIntrospectionSource('http://localhost/graphql');
  const schema = await source.load();
  const registry = new SchemaRegistry(schema);
  registry.warmUp(['Status', 'Empresa']);

  console.log('✔ Schema loaded and warmed up successfully.');

  const transport = new FetchTransport('http://localhost/graphql');
  const statusRepo = createRepository(registry, transport, 'Status');

  console.log('\n2. Testing findAll()...');
  const list = await statusRepo.findAll();
  console.log(`✔ findAll returned ${list.items.length} items.`);

  const testName = 'STATUS_TEST_' + Date.now();
  console.log(`\n3. Testing create({ nombre: "${testName}" })...`);
  const created = await statusRepo.create({ nombre: testName, label: 'Smoke Test Label' });
  console.log(`✔ Entity created with IRI: ${created.id}`);

  console.log(`\n4. Testing findById("${created.id}")...`);
  const fetched = await statusRepo.findById(created.id);
  console.log(`✔ Fetched entity name: ${fetched?.nombre}`);

  console.log(`\n5. Testing update("${created.id}", { label: "Updated Smoke Test Label" })...`);
  const updated = await statusRepo.update(created.id, { label: 'Updated Smoke Test Label' });
  console.log(`✔ Entity updated label: ${updated.label}`);

  console.log(`\n6. Testing remove("${created.id}")...`);
  const removed = await statusRepo.remove(created.id);
  console.log(`✔ Entity removed: ${removed}`);

  console.log('\n🎉 ALL SMOKE TESTS PASSED SUCCESSFULLY!');
}

main().catch((err) => {
  console.error('❌ Smoke test failed:', err);
  if (err.graphQLErrors) console.error('Full GraphQL errors:', JSON.stringify(err.graphQLErrors, null, 2));
  process.exit(1);
});
