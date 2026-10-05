<!--
  Íconos de Google Material Symbols compilados en el build (unplugin-icons): sin llamadas a
  servicios externos. Solo los que usa la página; mismo uso que el <icon> del
  frontend (`<icon name="x" />`).
-->
<template>
  <component :is="svg" v-if="svg" aria-hidden="true" class="inline-block shrink-0" :style="{ width: size, height: size }" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import WarningOutline from '~icons/material-symbols/warning-outline'
import AirlineSeatReclineNormal from '~icons/material-symbols/airline-seat-recline-normal'
import Undo from '~icons/material-symbols/undo'
import ArrowBack from '~icons/material-symbols/arrow-back'
import ArrowForward from '~icons/material-symbols/arrow-forward'
import SwapHoriz from '~icons/material-symbols/swap-horiz'
import ThumbUpOutline from '~icons/material-symbols/thumb-up-outline'
import ChatOutline from '~icons/material-symbols/chat-outline'
import DirectionsBusOutline from '~icons/material-symbols/directions-bus-outline'
import CalendarMonthOutline from '~icons/material-symbols/calendar-month-outline'
import EventRepeatOutline from '~icons/material-symbols/event-repeat-outline'
import CheckCircleOutline from '~icons/material-symbols/check-circle-outline'
import KeyboardArrowDown from '~icons/material-symbols/keyboard-arrow-down'
import KeyboardDoubleArrowDown from '~icons/material-symbols/keyboard-double-arrow-down'
import KeyboardDoubleArrowUp from '~icons/material-symbols/keyboard-double-arrow-up'
import ChevronLeft from '~icons/material-symbols/chevron-left'
import ChevronRight from '~icons/material-symbols/chevron-right'
import KeyboardArrowUp from '~icons/material-symbols/keyboard-arrow-up'
import CancelOutline from '~icons/material-symbols/cancel-outline'
import ScheduleOutline from '~icons/material-symbols/schedule-outline'
import WorkspacePremiumOutline from '~icons/material-symbols/workspace-premium-outline'
import CreditCardOutline from '~icons/material-symbols/credit-card-outline'
import Download from '~icons/material-symbols/download'
import OpenInNew from '~icons/material-symbols/open-in-new'
import VisibilityOutline from '~icons/material-symbols/visibility-outline'
import InfoOutline from '~icons/material-symbols/info-outline'
import LockOutline from '~icons/material-symbols/lock-outline'
import MailOutline from '~icons/material-symbols/mail-outline'
import LocationOnOutline from '~icons/material-symbols/location-on-outline'
import Menu from '~icons/material-symbols/menu'
import Package2Outline from '~icons/material-symbols/package-2-outline'
import CallOutline from '~icons/material-symbols/call-outline'
import FiberManualRecord from '~icons/material-symbols/fiber-manual-record'
import Route from '~icons/material-symbols/route'
import Search from '~icons/material-symbols/search'
import SendOutline from '~icons/material-symbols/send-outline'
import VerifiedUserOutline from '~icons/material-symbols/verified-user-outline'
import StarOutline from '~icons/material-symbols/star-outline'
import SportsMotorsportsOutline from '~icons/material-symbols/sports-motorsports-outline'
import ConfirmationNumberOutline from '~icons/material-symbols/confirmation-number-outline'
import DeleteOutline from '~icons/material-symbols/delete-outline'
import GroupOutline from '~icons/material-symbols/group-outline'
import Language from '~icons/material-symbols/language'
import Close from '~icons/material-symbols/close'

const ICONOS = {
  alert: WarningOutline,
  armchair: AirlineSeatReclineNormal,
  'arrow-back-up': Undo,
  'arrow-left': ArrowBack,
  'arrow-right': ArrowForward,
  'arrows-exchange': SwapHoriz,
  'brand-facebook': ThumbUpOutline,
  'brand-whatsapp': ChatOutline,
  bus: DirectionsBusOutline,
  calendar: CalendarMonthOutline,
  'calendar-repeat': EventRepeatOutline,
  check: CheckCircleOutline,
  'chevron-down': KeyboardArrowDown,
  'chevrons-down': KeyboardDoubleArrowDown,
  'chevrons-up': KeyboardDoubleArrowUp,
  'chevron-left': ChevronLeft,
  'chevron-right': ChevronRight,
  'chevron-up': KeyboardArrowUp,
  'circle-x': CancelOutline,
  clock: ScheduleOutline,
  crown: WorkspacePremiumOutline,
  'credit-card': CreditCardOutline,
  download: Download,
  'external-link': OpenInNew,
  eye: VisibilityOutline,
  info: InfoOutline,
  lock: LockOutline,
  mail: MailOutline,
  'map-pin': LocationOnOutline,
  menu: Menu,
  package: Package2Outline,
  phone: CallOutline,
  punto: FiberManualRecord,
  route: Route,
  search: Search,
  send: SendOutline,
  'shield-check': VerifiedUserOutline,
  star: StarOutline,
  'steering-wheel': SportsMotorsportsOutline,
  ticket: ConfirmationNumberOutline,
  trash: DeleteOutline,
  users: GroupOutline,
  world: Language,
  x: Close,
} as const

const props = withDefaults(defineProps<{ name: string; size?: string | number }>(), { size: '1.25rem' })
const svg = computed(() => ICONOS[props.name as keyof typeof ICONOS] ?? null)
const size = computed(() => (typeof props.size === 'number' ? `${props.size}px` : props.size))
</script>
