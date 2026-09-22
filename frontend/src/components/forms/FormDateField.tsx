import type { ReactNode } from 'react'
import { Controller, useFormContext, type FieldValues, type Path } from 'react-hook-form'
import TextField from '@mui/material/TextField'
import type { TextFieldProps } from '@mui/material/TextField'

type FormDateFieldProps<T extends FieldValues> = Omit<TextFieldProps, 'name' | 'value' | 'onChange' | 'onBlur' | 'ref' | 'type'> & {
  name: Path<T>
  label: string
}

/** Renders an HTML date input wired to react-hook-form (value is 'yyyy-mm-dd'). */
export function FormDateField<T extends FieldValues>({ name, label, ...rest }: FormDateFieldProps<T>): ReactNode {
  const { control } = useFormContext<T>()

  return (
    <Controller
      name={name}
      control={control}
      render={({ field, fieldState }) => (
        <TextField
          {...rest}
          id={name}
          label={label}
          type="date"
          slotProps={{ inputLabel: { shrink: true } }}
          name={field.name}
          value={field.value ?? ''}
          onChange={field.onChange}
          onBlur={field.onBlur}
          inputRef={field.ref}
          error={Boolean(fieldState.error)}
          helperText={fieldState.error ? fieldState.error.message : rest.helperText}
        />
      )}
    />
  )
}