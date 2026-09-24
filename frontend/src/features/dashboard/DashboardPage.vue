<template>
  <div>
    <PageHead
      title="Resumen operativo"
      subtitle="Guatemala, jueves 30 de julio · actualizado hace 2 minutos"
    >
    </PageHead>
    <div class="card">
      <Accordion value="0">
        <AccordionPanel value="0">
          <AccordionHeader>Card</AccordionHeader>
          <AccordionContent>
            <Card class="max-w-sm w-full overflow-hidden" :pt="{ body: { class: 'pt-16!' } }">
              <template #header>
                <div class="relative">
                  <img
                    class="w-full max-h-42 object-cover"
                    alt="user header"
                    src="https://images.unsplash.com/photo-1513649718256-1a7162666bad?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D"
                  />
                  <Avatar
                    shape="circle"
                    class="w-24! h-24! border-3 border-surface-0 dark:border-surface-900 absolute! -bottom-12! left-4!"
                  >
                    <img
                      src="https://images.unsplash.com/photo-1722495178488-c8056c4ec2c0?q=80&w=2081&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D"
                    />
                  </Avatar>
                </div>
              </template>
              <template #title>
                <span class="font-bold text-xl">Sakura Fresh Market</span>
              </template>
              <template #subtitle>
                <div class="flex items-center gap-2">
                  <Tag severity="info" value="Daily" />
                  <Tag severity="info" value="Premium" />
                </div>
              </template>
              <template #content>
                <div class="space-y-4">
                  <p>
                    Sakura Fresh Market is your go-to store for fresh local produce, Japanese
                    snacks, and daily essentials — all in one place!
                  </p>
                  <div class="flex items-center gap-2">
                    <!-- <StarFill class="text-yellow-500" /> -->
                    <span><b>4.6</b> (200+ reviews)</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <!-- <MapMarker /> -->
                    <span>Tokyo, Shibuya-ku</span>
                  </div>
                </div>
              </template>
              <template #footer>
                <div class="flex items-center gap-2 mt-4">
                  <Button severity="secondary" outlined class="flex-1">
                    <!-- <Phone /> -->
                    Call Us
                  </Button>
                  <Button class="flex-1">
                    <!-- <Globe /> -->
                    Visit Site
                  </Button>
                </div>
              </template>
            </Card>
          </AccordionContent>
        </AccordionPanel>
        <AccordionPanel value="1">
          <AccordionHeader>Dialog</AccordionHeader>
          <AccordionContent>
            <div class="card flex justify-center">
              <Button label="Show" @click="visible = true" />
              <Dialog
                v-model:visible="visible"
                modal
                header="Edit Profile"
                :style="{ width: '25rem' }"
              >
                <span class="text-surface-500 dark:text-surface-400 block mb-8"
                  >Update your information.</span
                >
                <div class="flex items-center gap-4 mb-4">
                  <label for="username" class="font-semibold w-24">Username</label>
                  <InputText id="username" class="flex-auto" autocomplete="off" />
                </div>
                <div class="flex items-center gap-4 mb-8">
                  <label for="email" class="font-semibold w-24">Email</label>
                  <InputText id="email" class="flex-auto" autocomplete="off" />
                </div>
                <div class="flex justify-end gap-2">
                  <Button
                    type="button"
                    label="Cancel"
                    severity="secondary"
                    @click="visible = false"
                  ></Button>
                  <Button type="button" label="Save" @click="visible = false"></Button>
                </div>
              </Dialog>
            </div>
          </AccordionContent>
        </AccordionPanel>
        <AccordionPanel value="2">
          <AccordionHeader>Header III</AccordionHeader>
          <AccordionContent>
            <p class="m-0">
              At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium
              voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint
              occaecati cupiditate non provident, similique sunt in culpa qui officia deserunt
              mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et
              expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque
              nihil impedit quo minus.
            </p>
          </AccordionContent>
        </AccordionPanel>
      </Accordion>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const visible = ref(false)
</script>
