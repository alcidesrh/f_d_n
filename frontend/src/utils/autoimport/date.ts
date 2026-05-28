import dayjs from "dayjs";

// export const dayjs = useDayjs();
export const dformat = (val: string) => {
  const t =
    dayjs(val).format("DD/MM/YYYY") +
    " <span>" +
    dayjs(val).format("hh:mm:ss a") +
    "</span>";
  return t;
};
export const cformat = (val: string) => dayjs(val).format("YYYYMMDDTHH:mm:ss");
export const month_day_hour_format = (val: string) =>
  dayjs(val).format("hh:mm:ss a"); // useDateFormat(val, 'DD/MMMM hh:mm:ss a');
