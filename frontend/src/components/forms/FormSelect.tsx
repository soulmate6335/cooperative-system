import type { ReactNode } from 'react'
import { Controller, useFormContext, type FieldValues, type Path } from 'react-hook-form'
import FormControl from '@mui/material/FormControl'
import FormHelperText from '@mui/material/FormHelperText'
import InputLabel from '@mui/material/InputLabel'
import MenuItem from '@mui/material/MenuItem'
import Select, { type SelectProps } from '@mui/material/Select'

export interface SelectOption {
  value: string | number
  label: string
}

type FormSelectProps<T extends FieldValues> = Omit<SelectProps, 'name' | 'value' | 'onChange' | 'onBlur'> & {
  name: Path<T>
  label: string
  options: SelectOption[]
  helperText?: string
}

export function FormSelect<T extends FieldValues>({ name, label, options, helperText, ...rest }: FormSelectProps<T>): ReactNode {
  const { control } = useFormContext<T>()

  return (
    <Controller
      name={name}
      control={control}
      render={({ field, fieldState }) => (
        <FormControl fullWidth error={Boolean(fieldState.error)}>
          <InputLabel id={`${name}-label`}>{label}</InputLabel>
          <Select
            {...rest}
            id={name}
            labelId={`${name}-label`}
            label={label}
            value={field.value ?? ''}
            onChange={field.onChange}
            onBlur={field.onBlur}
            inputRef={field.ref}
          >
            {options.map((option) => (
              <MenuItem key={String(option.value)} value={option.value}>
                {option.label}
              </MenuItem>
            ))}
          </Select>
          <FormHelperText>{fieldState.error ? fieldState.error.message : helperText}</FormHelperText>
        </FormControl>
      )}
    />
  )
}