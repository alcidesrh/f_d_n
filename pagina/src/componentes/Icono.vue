<!--
  Íconos de Tabler compilados en el build (unplugin-icons): sin llamadas a
  servicios externos. Solo los que usa la página; mismo uso que el <icon> del
  frontend (`<icon name="x" />`).
-->
<template>
  <component :is="svg" v-if="svg" aria-hidden="true" class="inline-block shrink-0" :style="{ width: size, height: size }" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AlertTriangle from '~icons/tabler/alert-triangle'
import Armchair from '~icons/tabler/armchair'
import ArrowBackUp from '~icons/tabler/arrow-back-up'
import ArrowLeft from '~icons/tabler/arrow-left'
import ArrowRight from '~icons/tabler/arrow-right'
import ArrowsExchange from '~icons/tabler/arrows-exchange'
import BrandFacebook from '~icons/tabler/brand-facebook'
import BrandWhatsapp from '~icons/tabler/brand-whatsapp'
import Bus from '~icons/tabler/bus'
import Calendar from '~icons/tabler/calendar'
import CalendarRepeat from '~icons/tabler/calendar-repeat'
import ChevronDown from '~icons/tabler/chevron-down'
import ChevronLeft from '~icons/tabler/chevron-left'
import ChevronRight from '~icons/tabler/chevron-right'
import ChevronUp from '~icons/tabler/chevron-up'
import ChevronsDown from '~icons/tabler/chevrons-down'
import ChevronsUp from '~icons/tabler/chevrons-up'
import CircleCheck from '~icons/tabler/circle-check'
import CircleX from '~icons/tabler/circle-x'
import Clock from '~icons/tabler/clock'
import Crown from '~icons/tabler/crown'
import CreditCard from '~icons/tabler/credit-card'
import Download from '~icons/tabler/download'
import ExternalLink from '~icons/tabler/external-link'
import Eye from '~icons/tabler/eye'
import InfoCircle from '~icons/tabler/info-circle'
import Lock from '~icons/tabler/lock'
import Mail from '~icons/tabler/mail'
import MapPin from '~icons/tabler/map-pin'
import Menu2 from '~icons/tabler/menu-2'
import Package from '~icons/tabler/package'
import Phone from '~icons/tabler/phone'
import PointFilled from '~icons/tabler/point-filled'
import Route from '~icons/tabler/route'
import Search from '~icons/tabler/search'
import Send from '~icons/tabler/send'
import ShieldCheck from '~icons/tabler/shield-check'
import Star from '~icons/tabler/star'
import SteeringWheel from '~icons/tabler/steering-wheel'
import Ticket from '~icons/tabler/ticket'
import Trash from '~icons/tabler/trash'
import Users from '~icons/tabler/users'
import World from '~icons/tabler/world'
import X from '~icons/tabler/x'

const ICONOS = {
  alert: AlertTriangle,
  armchair: Armchair,
  'arrow-back-up': ArrowBackUp,
  'arrow-left': ArrowLeft,
  'arrow-right': ArrowRight,
  'arrows-exchange': ArrowsExchange,
  'brand-facebook': BrandFacebook,
  'brand-whatsapp': BrandWhatsapp,
  bus: Bus,
  calendar: Calendar,
  'calendar-repeat': CalendarRepeat,
  check: CircleCheck,
  'chevron-down': ChevronDown,
  'chevrons-down': ChevronsDown,
  'chevrons-up': ChevronsUp,
  'chevron-left': ChevronLeft,
  'chevron-right': ChevronRight,
  'chevron-up': ChevronUp,
  'circle-x': CircleX,
  clock: Clock,
  crown: Crown,
  'credit-card': CreditCard,
  download: Download,
  'external-link': ExternalLink,
  eye: Eye,
  info: InfoCircle,
  lock: Lock,
  mail: Mail,
  'map-pin': MapPin,
  menu: Menu2,
  package: Package,
  phone: Phone,
  punto: PointFilled,
  route: Route,
  search: Search,
  send: Send,
  'shield-check': ShieldCheck,
  star: Star,
  'steering-wheel': SteeringWheel,
  ticket: Ticket,
  trash: Trash,
  users: Users,
  world: World,
  x: X,
} as const

const props = withDefaults(defineProps<{ name: string; size?: string | number }>(), { size: '1.25rem' })
const svg = computed(() => ICONOS[props.name as keyof typeof ICONOS] ?? null)
const size = computed(() => (typeof props.size === 'number' ? `${props.size}px` : props.size))
</script>
